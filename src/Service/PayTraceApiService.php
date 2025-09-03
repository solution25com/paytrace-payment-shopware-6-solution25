<?php

namespace PayTrace\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use PayTrace\Library\Constants\Endpoints;
use PayTrace\Library\Constants\EnvironmentUrl;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Exception\RequestException;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PayTraceApiService extends Endpoints
{
  private PayTraceConfigService $payTraceConfigService;
  private PayTraceCustomerVaultService $payTraceCustomerVaultService;
  private LoggerInterface $logger;
  private Client $client;
  private ?string $authToken = null;
  private ?int $authTokenExpiryTime = null;

  public function __construct(PayTraceConfigService $payTraceConfigService, PayTraceCustomerVaultService $payTraceCustomerVaultService, LoggerInterface $logger)
  {
    $this->payTraceConfigService = $payTraceConfigService;
    $this->payTraceCustomerVaultService = $payTraceCustomerVaultService;
    $this->logger = $logger;
    $this->client = new Client();
  }

  private function getAuthorizationToken(?string $salesChannelId = ''): string
  {
    if ($this->authToken && time() < $this->authTokenExpiryTime) {
      return $this->authToken;
    }

    $baseUrl = $this->getModeUrl($salesChannelId);

    $fullEndpointUrl = Endpoints::getUrl($baseUrl,Endpoints::AUTH_TOKEN);
    $body = $this->buildRequestBody($salesChannelId);

    $options = [
      'form_params' => $body,
      'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
    ];

    $response = $this->request($fullEndpointUrl, $options);

    if ($response instanceof Response) {
      $responseBody = $response->getBody()->getContents();
      $decodedBody = json_decode($responseBody, true);

      if (isset($decodedBody['error']) && $decodedBody['error']) {
        return "Error: " . ($decodedBody['message'] ?? 'Unknown error');
      }

      $this->authToken = $decodedBody['data']['access_token'] ?? 'Unknown error';
      $this->authTokenExpiryTime = time() + 900;

      return $this->authToken;
    }

    return "Error: Invalid response received";
  }

  public function generatePaymentToken(?string $salesChannelId = ''): string|array
  {
    $baseUrl = $this->getModeUrl($salesChannelId);
    $fullEndpointUrl = Endpoints::getUrl($baseUrl, Endpoints::PAYMENT_FIELD_TOKENS);

    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->getAuthorizationToken($salesChannelId),
        'X-Integrator-Id' => '9999Shopware',
        'Content-Type' => 'application/json',
      ],
    ];

    $response = $this->request($fullEndpointUrl, $options);

    if ($response instanceof Response) {
      $responseBody = $response->getBody()->getContents();
      $dec = json_decode($responseBody, true);
      return $dec['data']['clientKey'] ?? 'Error: No client key returned';
    }

    return ['error' => true, 'message' => 'Invalid response received'];
  }


  public function processPayment(array $token, string $amount, array $billingData, bool $saveCard, SalesChannelContext $context): ResponseInterface|array
  {
    $baseUrl = $this->getModeUrl($context->getSalesChannelId());

    $fullEndpointUrl = $saveCard ? Endpoints::getUrl($baseUrl,Endpoints::CUSTOMER_TRANSACTION) : Endpoints::getUrl($baseUrl,Endpoints::TRANSACTION);

    $customerId = $context->getCustomer()?->getId();
    $label = '';

    if ($saveCard && $customerId) {
      $label = $this->payTraceCustomerVaultService->getNextCardLabel($context);
    }

    $body = [
      'hpf_token' => $token['hpf_token'],
      'enc_key' => $token['enc_key'],
      'amount' => $amount,
      'billing_address' => [
        'street' => $billingData['street'],
        'street2' => $billingData['street2'] ?? null,
        'city' => $billingData['city'],
        'state' => $billingData['state'],
        'country' => $billingData['country'],
        'postal_code' => $billingData['zip'],
      ],
      'billing_name' => $billingData['fullName'],
      'merchant_id' => $this->payTraceConfigService->getConfig('merchantId') ?? '',
    ];

    if ($saveCard) {
      $body['create_customer'] = true;
      $body['customer_label'] = $label;
    }

    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->getAuthorizationToken($context->getSalesChannelId()),
        'X-Integrator-Id' => '9999Shopware',
        'X-Permalinks' => true,
        'Content-Type' => 'application/json',
      ],
      'body' => json_encode($body),
    ];

    $response = $this->request($fullEndpointUrl, $options);
    $apiResponse = $this->ApiResponse($response);

    $success = !($apiResponse['error'] ?? true);

    if ($saveCard && $success) {
      $vaultId = $apiResponse['data']['customer_id'] ?? null;

      if ($vaultId) {
        $customerDetails = $this->getCustomerProfile($vaultId, $context);

        $this->payTraceCustomerVaultService->storeCardFromCustomerDetails($vaultId, $billingData['fullName'], $customerDetails, $context);
      }
    }
    return $apiResponse;
  }

  public function processEcheckDeposit(array $data, array $billingData, $salesChannelId): array
  {
    $baseUrl = $this->getModeUrl($salesChannelId);
    $fullEndpointUrl = Endpoints::getUrl($baseUrl,Endpoints::ACH_DEPOSIT);

    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->getAuthorizationToken($salesChannelId),
        'X-Integrator-Id' => '9999Shopware',
        'X-Permalinks' => true,
        'Content-Type' => 'application/json',
      ],
      'body' => json_encode([
        'std_entry_class' => $data['stdEntryClass'] ?? 'WEB',
        'billing_name' => $data['billingName'] ?? '',
        'billing_email' => $billingData['email'] ?? '',
        'shipping_name' => $data['billingName'] ?? '',
        'bank_account' => [
          'account_number' => $data['accountNumber'],
          'routing_number' => $data['routingNumber'],
          'account_type' => $data['accountType'] ?? 'Checking'
        ],
        'amount' => $data['amount'],
        'merchant_id' => $this->payTraceConfigService->getConfig('merchantId') ?? '',
        'billing_address' => [
          'street' => $billingData['street'] ?? '',
          'street2' => $billingData['street2'] ?? '',
          'city' => $billingData['city'] ?? '',
          'state' => $billingData['state'] ?? '',
          'country' => $billingData['country'] ?? '',
          'postal_code' => $billingData['zip'] ?? '',
        ],
        'shipping_address' => [
          'street' => $billingData['street'] ?? '',
          'street2' => $billingData['street2'] ?? '',
          'city' => $billingData['city'] ?? '',
          'state' => $billingData['state'] ?? '',
          'country' => $billingData['country'] ?? '',
          'postal_code' => $billingData['zip'] ?? '',
        ],
      ]),
    ];

    $response = $this->request($fullEndpointUrl, $options);
    return $this->ApiResponse($response);
  }

  public function processPaymentAuthorize(array $token, string $amount, array $billingData, $saveCard, SalesChannelContext $context): ResponseInterface|array
  {
    $baseUrl = $this->getModeUrl($context->getSalesChannelId());
    $fullEndpointUrl = Endpoints::getUrl($baseUrl, Endpoints::AUTHORIZE);

    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->getAuthorizationToken($context->getSalesChannelId()),
        'X-Integrator-Id' => '9999Shopware',
        'X-Permalinks' => true,
        'Content-Type' => 'application/json',
      ],
      'body' => json_encode([
        'hpf_token' => $token['hpf_token'],
        'enc_key' => $token['enc_key'],
        'amount' => $amount,
        'billing_address' => [
          'street' => $billingData['street'],
          'street2' => $billingData['street2'] ?? null,
          'city' => $billingData['city'],
          'state' => $billingData['state'],
          'country' => $billingData['country'],
          'postal_code' => $billingData['zip'],
        ],
        'billing_name' => $billingData['fullName'],
        'merchant_id' => $this->payTraceConfigService->getConfig('merchantId') ?? '',
      ]),
    ];

    $response = $this->request($fullEndpointUrl, $options);
    $apiResponse = $this->ApiResponse($response);

    $success = !($apiResponse['error'] ?? true);

    if ($saveCard && $success) {
      $transactionId = $apiResponse['data']['transaction_id'] ?? null;
      if ($transactionId) {
        $customerCreateResponse = $this->createCustomerByTransaction($transactionId, $billingData, $context);

        if (!($customerCreateResponse['error'] ?? true)) {
          $vaultId = $customerCreateResponse['data']['customer_id'];

          $customerDetails = $this->getCustomerProfile($vaultId, $context);

          $this->payTraceCustomerVaultService->storeCardFromCustomerDetails($vaultId, $billingData['fullName'], $customerDetails, $context);
        }
      }
    }

    return $apiResponse;
  }

  public function processCapture(array $data, $salesChannelId): ResponseInterface|array
  {
    $baseUrl = $this->getModeUrl($salesChannelId);
    $fullEndpointUrl = Endpoints::getUrl($baseUrl,Endpoints::CAPTURE);

    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->getAuthorizationToken($salesChannelId),
        'X-Integrator-Id' => '9999Shopware',
        'X-Permalinks' => true,
        'Content-Type' => 'application/json',
      ],
      'body' => json_encode([
        'merchant_id' => $this->payTraceConfigService->getConfig('merchantId') ?? '',
        'batch_items' => [
          [
            'transaction_id' => $data['transactionId'],
            'custom_dba' => 'Capturing :' . $data['amount'] . 'from transaction: ' . $data['transactionId'],
            'amount' => $data['amount']
          ]
        ]
      ]),
    ];

    $response = $this->request($fullEndpointUrl, $options);
    return $this->ApiResponse($response);
  }

  public function captureRefund(array $data, $salesChannelId): string|array
  {
    $baseUrl = $this->getModeUrl($salesChannelId);
    $fullEndpointUrl = Endpoints::getUrl($baseUrl,Endpoints::REFUND);

    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->getAuthorizationToken($salesChannelId),
        'X-Integrator-Id' => '9999Shopware',
        'X-Permalinks' => true,
        'Content-Type' => 'application/json',
      ],
      'body' => json_encode([
        'merchant_id' => $this->payTraceConfigService->getConfig('merchantId') ?? '',
        'batch_items' => [
          [
            'transaction_id' => $data['transactionId'],
            'custom_dba' => 'Refund amount: ' . $data['amount'] . 'from transaction: ' . $data['transactionId'],
            'amount' => $data['amount']
          ]
        ]
      ]),
    ];

    $response = $this->request($fullEndpointUrl, $options);
    return $this->ApiResponse($response);
  }

  public function voidTransaction(array $data, $salesChannelId): string|array
  {
    $baseUrl = $this->getModeUrl($salesChannelId);
    $fullEndpointUrl = Endpoints::getUrl($baseUrl,Endpoints::VOID);

    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->getAuthorizationToken($salesChannelId),
        'X-Integrator-Id' => '9999Shopware',
        'X-Permalinks' => true,
        'Content-Type' => 'application/json',
      ],
      'body' => json_encode([
        'merchant_id' => $this->payTraceConfigService->getConfig('merchantId') ?? '',
        'batch_items' => [
          [
            'transaction_id' => $data['transactionId'],
            'custom_dba' =>  'Void transaction: ' . $data['transactionId'],
          ]
        ]
      ]),
    ];

    $response = $this->request($fullEndpointUrl, $options);
    return $this->ApiResponse($response);
  }

  public function processVaultedPayment(array $data, SalesChannelContext $context): ResponseInterface|array
  {
    if (empty($data['selectedCardVaultedId'])) {
      return ['error' => true, 'message' => 'Missing customer ID'];
    }

    $endpointDetails = Endpoints::getUrlDynamicParam(
      $this->getModeUrl($context->getSalesChannelId()),
      Endpoints::VAULTED_TRANSACTION,
      [$data['selectedCardVaultedId']],
    );

    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->getAuthorizationToken($context->getSalesChannelId()),
        'X-Integrator-Id' => '9999Shopware',
        'X-Permalinks' => true,
        'Content-Type' => 'application/json',
      ],
      'body' => json_encode([
        'amount' => $data['amount'],
        'merchant_id' => $this->payTraceConfigService->getConfig('merchantId') ?? '',
      ]),
    ];

    $response = $this->request($endpointDetails, $options);
    return $this->ApiResponse($response);
  }

  public function createCustomerProfile(array $data, SalesChannelContext $context): ResponseInterface | array
  {
    $baseUrl = $this->getModeUrl($context->getSalesChannelId());
    $fullEndpointUrl = Endpoints::getUrl($baseUrl, Endpoints::ADD_CARD);

    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->getAuthorizationToken($context->getSalesChannelId()),
        'X-Integrator-Id' => '9999Shopware',
        'X-Permalinks' => true,
        'Content-Type' => 'application/json',
      ],
      'body' => json_encode([
        'hpf_token' => $data['cardToken']['hpf_token'],
        'enc_key' => $data['cardToken']['enc_key'],
        'merchant_id' => $this->payTraceConfigService->getConfig('merchantId') ?? '',
        'billing_address' => [
          'name' => $data['billing_address']['name'],
          'street_address' => $data['billing_address']['street_address'],
          'street_address2' => $data['billing_address']['street_address2'] ?? null,
          'city' => $data['billing_address']['city'],
          'state' => $data['billing_address']['state'],
          'postal_code' => $data['billing_address']['postal_code'],
          'country' => $data['billing_address']['country'],
        ],
        'customer_label' => $data['cardCount'],
      ]),
    ];

    $response = $this->request($fullEndpointUrl, $options);
    return $this->ApiResponse($response);
  }

  public function getCustomerProfile(string $vaultedCustomerId, SalesChannelContext $context): array
  {
    $endpoint = Endpoints::getUrlDynamicParam($this->getModeUrl($context->getSalesChannelId()),Endpoints::CUSTOMER_PROFILE, [$vaultedCustomerId]);
    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->getAuthorizationToken($context->getSalesChannelId()),
        'X-Integrator-Id' => '9999Shopware',
        'X-Permalinks' => true,
        'Content-Type' => 'application/json',
      ]
    ];

    $response = $this->request($endpoint, $options);
    return $this->ApiResponse($response);
  }


  public function deleteVaultedCard(string $vaultedCustomerId, SalesChannelContext $context): ResponseInterface | array
  {
    $endpointDetails = Endpoints::getUrlDynamicParam(
      $this->getModeUrl($context->getSalesChannelId()),
      Endpoints::DELETE_CARD,
      [$vaultedCustomerId],
    );

    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->getAuthorizationToken($context->getSalesChannelId()),
        'X-Integrator-Id' => '9999Shopware',
        'X-Permalinks' => true,
        'Content-Type' => 'application/json',
      ],
    ];

    $response = $this->request($endpointDetails, $options);

    if ($response instanceof Response) {
      $responseBody = $response->getBody()->getContents();
      $decodedResponse = json_decode($responseBody, true);

      if (isset($decodedResponse['error']) && $decodedResponse['error']) {
        return [
          'error' => true,
          'message' => $decodedResponse['message'] ?? 'Unknown error',
        ];
      }

      return [
        'error' => false,
        'message' => 'Success',
        'data' => $decodedResponse['data'] ?? [],
      ];
    }

    return [
      'error' => true,
      'message' => 'Invalid response received',
    ];
  }

  private function buildRequestBody(?string $salesChannelId = ''): array
  {
    return [
      'grant_type'    => 'client_credentials',
      'client_id'     => $this->payTraceConfigService->getConfig('clientIdSandbox', $salesChannelId ),
      'client_secret' => $this->payTraceConfigService->getConfig('clientSecretSandbox',$salesChannelId ) ?? '',
    ];
  }

  private function ApiResponse($response): array
  {
    if (is_array($response)) {
      $decodedResponse = $response;
    } elseif ($response instanceof ResponseInterface) {
      $responseBody = $response->getBody()->getContents();
      $decodedResponse = json_decode($responseBody, true);
    } else {
      return [
        'error' => true,
        'message' => 'Invalid response type',
      ];
    }

    if (isset($decodedResponse['error']) && $decodedResponse['error']) {
      $errorField = $decodedResponse['message']['data'][0]['field'] ?? null;

      if ($errorField === 'path/record_id') {
        return [
          'error' => false,
          'message' => 'Skipped',
          'data' => $decodedResponse['data'] ?? null,
        ];
      }

      return [
        'error' => true,
        'message' => $decodedResponse['message']['data'][0]['detail'] ?? 'Unknown error',
      ];
    }

    if (isset($decodedResponse['data']['response_code'])) {
      $errorCodes = [1, 102, 103, 107, 110, 113, 125, 167];
      if (in_array($decodedResponse['data']['response_code'], $errorCodes)) {
        return [
          'error' => true,
          'message' => $decodedResponse['data']['response_message'] ?? 'Unknown error',
        ];
      }
    }

    return [
      'error' => false,
      'message' => 'Success',
      'data' => $decodedResponse['data'] ?? null,
    ];
  }

  private function handleError(GuzzleException $e): array
  {
    if ($e instanceof RequestException && $e->hasResponse()) {
      $response = $e->getResponse();

      if ($response instanceof ResponseInterface) {
        $responseBody = $response->getBody()->getContents();
        $decodedBody = json_decode($responseBody, true);

        return [
          'error' => true,
          'code' => $e->getCode(),
          'message' => $decodedBody['message'] ?? $decodedBody,
        ];
      }
    }

    return [
      'error' => true,
      'code' => $e->getCode(),
      'message' => $e->getMessage(),
    ];
  }

  private function request(array $endpoint, array $options): ResponseInterface|array
  {
    try {
      ['method' => $method, 'url' => $url] = $endpoint;
      return $this->client->request($method, $url, $options);
    } catch (GuzzleException $e) {
      return $this->handleError($e);
    }
  }

  public function createCustomerByTransaction(string $transactionId, array $billingData, SalesChannelContext $context): array
  {

    $customerId = $context->getCustomer()?->getId();
    if (!$customerId) {
      return [
        'error' => true,
        'message' => 'Customer not found',
      ];
    }

    $baseUrl = $this->getModeUrl($context->getSalesChannelId());

    $url = Endpoints::getUrl($baseUrl,Endpoints::CREATE_CUSTOMER_BY_TRANSACTION);

    $customerLabel = $this->payTraceCustomerVaultService->getNextCardLabel($context);

    $body = [
      'transaction_id' => $transactionId,
      'billing_address' => [
        'name' => $billingData['fullName'],
        'street_address' => $billingData['street'],
        'street_address2' => $billingData['street2'] ?? '',
        'city' => $billingData['city'],
        'state' => $billingData['state'],
        'postal_code' => $billingData['zip'],
        'country' => $billingData['country'],
      ],
      'customer_label' => $customerLabel,
    ];

    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->getAuthorizationToken($context->getSalesChannelId()),
        'X-Integrator-Id' => '9999Shopware',
        'X-Permalinks' => true,
        'Content-Type' => 'application/json',
      ],
      'body' => json_encode($body),
    ];

    return $this->ApiResponse($this->request($url, $options));
  }
  public function testConnection(string $salesChannelId): bool
  {
    try {
      $mode   = $this->payTraceConfigService->getConfig('mode', $salesChannelId);
      $isLive = $mode === 'live';

      $baseUrl = $this->getModeUrl($salesChannelId);

      $clientId = $this->payTraceConfigService->getConfig(
        $isLive ? 'clientIdProd' : 'clientIdSandbox',
        $salesChannelId
      );
      $clientSecret = $this->payTraceConfigService->getConfig(
        $isLive ? 'clientSecretProd' : 'clientSecretSandbox',
        $salesChannelId
      );

      $body = [
        'grant_type'    => 'client_credentials',
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
      ];

      $options = [
        'form_params' => $body,
        'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
      ];

      $endpoint = [
        'method' => 'POST',
        'url'    => $baseUrl . '/v3/token',
      ];

      $response = $this->request($endpoint, $options);

      if ($response->getStatusCode() === 201) {
        return true;
      }

      return false;
    } catch (\Throwable $e) {
      $this->logger->error($e->getMessage());
      return false;
    }
  }
  public function getModeUrl(?string $salesChannelId = ''): string
  {
   $mode =  $this->payTraceConfigService->getConfig('mode', $salesChannelId);
   if($mode === 'live'){
     return EnvironmentUrl::PRODUCTION;
   }
   return EnvironmentUrl::SANDBOX;
  }
}
