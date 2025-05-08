<?php

namespace PayTrace\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use PayTrace\Library\Constants\Endpoints;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class PayTraceApiService extends Endpoints
{
  private PayTraceConfigService $payTraceConfigService;
  private LoggerInterface $logger;
  private Client $client;
  private ?string $authToken = null;
  private ?int $authTokenExpiryTime = null;

  public function __construct(PayTraceConfigService $payTraceConfigService, LoggerInterface $logger)
  {
    $this->payTraceConfigService = $payTraceConfigService;
    $this->logger = $logger;
    $this->client = new Client();
  }

  private function getAuthorizationToken(): string
  {
    if ($this->authToken && time() < $this->authTokenExpiryTime) {
      return $this->authToken;
    }

    $fullEndpointUrl = Endpoints::getUrl(Endpoints::AUTH_TOKEN);
    $body = $this->buildRequestBody();

    $options = [
      'form_params' => $body,
      'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
    ];

    $start = microtime(true);
    $res = $this->request($fullEndpointUrl, $options);
    $end = microtime(true);
    $executionTime = round($end - $start, 2);
    $this->logger->alert('ExecutionTime for Bearer token: ' . $executionTime);

    if ($res instanceof Response) {
      $responseBody = $res->getBody()->getContents();
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

  public function generatePaymentToken(): string|array
  {
    $fullEndpointUrl = Endpoints::getUrl(Endpoints::PAYMENT_FIELD_TOKENS);

    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->getAuthorizationToken(),
        'X-Integrator-Id' => $this->payTraceConfigService->getConfig('integratorId') ?? '',
        'Content-Type' => 'application/json',
      ],
    ];

    $start = microtime(true);
    $response = $this->request($fullEndpointUrl, $options);
    $end = microtime(true);
    $executionTime = round($end - $start, 2);
    $this->logger->alert('ExecutionTime for PaymentToken: ' . $executionTime);


    if ($response instanceof Response) {
      $responseBody = $response->getBody()->getContents();
      $dec = json_decode($responseBody, true);
      return $dec['data']['clientKey'] ?? 'Error: No client key returned';
    }

    return ['error' => true, 'message' => 'Invalid response received'];
  }


  public function processPayment(array $token, string $amount, array $billingData): ResponseInterface|array
  {
    $fullEndpointUrl = Endpoints::getUrl(Endpoints::TRANSACTION);

    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->getAuthorizationToken(),
        'X-Integrator-Id' => $this->payTraceConfigService->getConfig('integratorId') ?? '',
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

    if ($response instanceof Response) {
      $responseBody = $response->getBody()->getContents();
      return json_decode($responseBody, true);
    }

    return ['error' => true, 'message' => 'Invalid response received'];
  }

  public function processEcheckDeposit(array $data): ResponseInterface|array
  {
    $fullEndpointUrl = Endpoints::getUrl(Endpoints::ACH_DEPOSIT);

    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->getAuthorizationToken(),
        'X-Integrator-Id' => $this->payTraceConfigService->getConfig('integratorId')  ?? '',
        'X-Permalinks' => true,
        'Content-Type' => 'application/json',
      ],
      'body' => json_encode([
        'std_entry_class' => $data['stdEntryClass'] ?? 'WEB',
        'billing_name' => $data['billingName'] ?? '',
        'bank_account' => [
          'account_number' => $data['accountNumber'],
          'routing_number' => $data['routingNumber'],
          'account_type' => $data['accountType'] ?? 'Checking',
        ],
        'amount' => $data['amount'],
        'merchant_id' => $this->payTraceConfigService->getConfig('merchantId') ?? '',
      ]),
    ];

    $response = $this->request($fullEndpointUrl, $options);

    if ($response instanceof Response) {
      $responseBody = $response->getBody()->getContents();
      return json_decode($responseBody, true);
    }

    return ['error' => true, 'message' => 'Invalid response received'];
  }


  public function processPaymentAuthorize(array $token, string $amount, array $billingData): ResponseInterface|array
  {
    $fullEndpointUrl = Endpoints::getUrl(Endpoints::AUTHORIZE);

    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->getAuthorizationToken(),
        'X-Integrator-Id' => $this->payTraceConfigService->getConfig('integratorId') ?? '',
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


    $start = microtime(true);
    $response = $this->request($fullEndpointUrl, $options);
    $end = microtime(true);
    $executionTime = round($end - $start, 2);
    $this->logger->alert('ExecutionTime for authorization: ' . $executionTime);


    if ($response instanceof Response) {
      $responseBody = $response->getBody()->getContents();
      return json_decode($responseBody, true);
    }

    return ['error' => true, 'message' => 'Invalid response received'];
  }

  public function processCapture(array $data): ResponseInterface|array
  {
    $fullEndpointUrl = Endpoints::getUrl(Endpoints::CAPTURE);

    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->getAuthorizationToken(),
        'X-Integrator-Id' => $this->payTraceConfigService->getConfig('integratorId') ?? '',
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

    if ($response instanceof Response) {
      $responseBody = $response->getBody()->getContents();
      return json_decode($responseBody, true);
    }

    return ['error' => true, 'message' => 'Invalid response received'];
  }

  public function captureRefund(array $data): string|array
  {
    $fullEndpointUrl = Endpoints::getUrl(Endpoints::REFUND);

    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->getAuthorizationToken(),
        'X-Integrator-Id' => $this->payTraceConfigService->getConfig('integratorId') ?? '',
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

    if ($response instanceof Response) {
      $responseBody = $response->getBody()->getContents();
      $dec = json_decode($responseBody, true);

      if (isset($dec['data'])) {
        return $dec['data'];
      }

      return ['error' => 'No data returned from API'];
    }

    return ['error' => 'Network or request error occurred'];
  }

  public function voidTransaction(array $data): string|array
  {
    $fullEndpointUrl = Endpoints::getUrl(Endpoints::VOID);

    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->getAuthorizationToken(),
        'X-Integrator-Id' => $this->payTraceConfigService->getConfig('integratorId') ?? '',
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

    if ($response instanceof Response) {
      $responseBody = $response->getBody()->getContents();
      $dec = json_decode($responseBody, true);

      if (isset($dec['data'])) {
        return $dec['data'];
      }

      return ['error' => 'No data returned from API'];
    }

    return ['error' => 'Network or request error occurred'];
  }

  public function processVaultedPayment(array $data): ResponseInterface|array
  {
    if (empty($data['selectedCardVaultedId'])) {
      return ['error' => true, 'message' => 'Missing customer ID'];
    }

    $endpointDetails = Endpoints::getUrlDynamicParam(
      Endpoints::VAULTED_TRANSACTION,
      [$data['selectedCardVaultedId']],
    );

    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->getAuthorizationToken(),
        'X-Integrator-Id' => $this->payTraceConfigService->getConfig('integratorId') ?? '',
        'X-Permalinks' => true,
        'Content-Type' => 'application/json',
      ],
      'body' => json_encode([
        'amount' => $data['amount'],
        'merchant_id' => $this->payTraceConfigService->getConfig('merchantId') ?? '',
      ]),
    ];

    $response = $this->request($endpointDetails, $options);

    if ($response instanceof Response) {
      $responseBody = $response->getBody()->getContents();
      return json_decode($responseBody, true);
    }

    return ['error' => true, 'message' => 'Invalid response received'];
  }

  public function createCustomerProfile(array $data): ResponseInterface | array
  {
    $fullEndpointUrl = Endpoints::getUrl(Endpoints::ADD_CARD);

    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->getAuthorizationToken(),
        'X-Integrator-Id' => $this->payTraceConfigService->getConfig('integratorId') ?? '',
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
        'customer_label' => $data['customerId'] . $data['cardCount'],
      ]),
    ];

    $start = microtime(true);
    $response = $this->request($fullEndpointUrl, $options);
    $end = microtime(true);
    $executionTime = round($end - $start, 2);
    $this->logger->alert('ExecutionTime for authorization: ' . $executionTime);

    return $this->ApiResponse($response);
  }

  public function getCustomerProfile(string $vaultedCustomerId): array
  {
    $endpoint = Endpoints::getUrlDynamicParam(Endpoints::CUSTOMER_PROFILE, [$vaultedCustomerId]);
    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->getAuthorizationToken(),
        'X-Integrator-Id' => $this->payTraceConfigService->getConfig('integratorId') ?? '',
        'X-Permalinks' => true,
        'Content-Type' => 'application/json',
      ]
    ];
    $response = $this->request($endpoint, $options);
    return $this->ApiResponse($response);
  }


  public function deleteVaultedCard(string $vaultedCustomerId): ResponseInterface | array
  {
    $endpointDetails = Endpoints::getUrlDynamicParam(
      Endpoints::DELETE_CARD,
      [$vaultedCustomerId],
    );

    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->getAuthorizationToken(),
        'X-Integrator-Id' => $this->payTraceConfigService->getConfig('integratorId') ?? '',
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

  private function buildRequestBody(): array
  {
    return [
      'grant_type'    => 'client_credentials',
      'client_id'     => $this->payTraceConfigService->getConfig('clientIdSandbox') ?? '',
      'client_secret' => $this->payTraceConfigService->getConfig('clientSecretSandbox') ?? '',
    ];
  }

  private function ApiResponse(ResponseInterface $response): array
  {
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
      'data' => $decodedResponse['data'],
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

  private function handleError(GuzzleException $e): array
  {
    $response = $e->hasResponse() ? $e->getResponse() : null;
    if ($response) {
      $responseBody = $response->getBody()->getContents();
      $decodedBody = json_decode($responseBody, true);
      return [
        'error' => true,
        'code' => $e->getCode(),
        'message' => $decodedBody['message'] ?? $decodedBody,
      ];
    }

    return [
      'error' => true,
      'code' => $e->getCode(),
      'message' => $e->getMessage(),
    ];
  }
}
