<?php

namespace solu1Paytrace\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Payment\PaymentException;
use solu1Paytrace\Library\Constants\Endpoints;
use solu1Paytrace\Library\Constants\EnvironmentUrl;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PayTraceApiService extends Endpoints
{
    private PayTraceConfigService $payTraceConfigService;
    private PayTraceCustomerVaultService $payTraceCustomerVaultService;
    private LoggerInterface $logger;
    private Client $client;
    private ?string $authToken = null;
    private ?int $authTokenExpiryTime = null;

    public function __construct(
        PayTraceConfigService $payTraceConfigService,
        PayTraceCustomerVaultService $payTraceCustomerVaultService,
        LoggerInterface $logger
    ) {
        $this->payTraceConfigService = $payTraceConfigService;
        $this->payTraceCustomerVaultService = $payTraceCustomerVaultService;
        $this->logger = $logger;
        $this->client = new Client();
    }

    /**
     * @throws \Exception
     */
    private function getAuthorizationToken(?string $salesChannelId = ''): string
    {
        if ($this->authToken && time() < $this->authTokenExpiryTime) {
            return $this->authToken;
        }

        $baseUrl = $this->getModeUrl($salesChannelId);

        /** @var array{method:string,url:string} $fullEndpointUrl */
        $fullEndpointUrl = Endpoints::getUrl($baseUrl, Endpoints::AUTH_TOKEN);
        $body = $this->buildRequestBody($salesChannelId);

        /** @var array<string,mixed> $options */
        $options = [
            'form_params' => $body,
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
        ];

        $response = $this->request($fullEndpointUrl, $options);

        if ($response instanceof Response) {
            $responseBody = $response->getBody()->getContents();
            /** @var array<string,mixed>|null $decodedBody */
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

    /**
     * @return string|array<string,mixed>
     * @throws \Exception
     */
    public function generatePaymentToken(?string $salesChannelId = ''): string|array
    {
        $baseUrl = $this->getModeUrl($salesChannelId);
        /** @var array{method:string,url:string} $fullEndpointUrl */
        $fullEndpointUrl = Endpoints::getUrl($baseUrl, Endpoints::PAYMENT_FIELD_TOKENS);

        /** @var array<string,mixed> $options */
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
            /** @var array<string,mixed>|null $dec */
            $dec = json_decode($responseBody, true);
            return $dec['data']['clientKey'] ?? 'Error: No client key returned';
        }

        throw new PaymentException(400, PaymentException::PAYMENT_INVALID_TOKEN, 'Invalid response received');
    }

    /**
     * @param array<string,mixed> $token
     * @param array<string,mixed> $billingData
     * @return ResponseInterface|array<string,mixed>
     * @throws \Exception
     */
    public function processPayment(array $token, string $amount, array $billingData, bool $saveCard, SalesChannelContext $context): ResponseInterface|array
    {
        $baseUrl = $this->getModeUrl($context->getSalesChannelId());

        /** @var array{method:string,url:string} $fullEndpointUrl */
        $fullEndpointUrl = $saveCard
            ? Endpoints::getUrl($baseUrl, Endpoints::CUSTOMER_TRANSACTION)
            : Endpoints::getUrl($baseUrl, Endpoints::TRANSACTION);

        $customerId = $context->getCustomer()?->getId();
        $label = '';

        if ($saveCard && $customerId) {
            $label = $this->payTraceCustomerVaultService->getNextCardLabel($context);
        }

        /** @var array<string,mixed> $body */
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

        /** @var array<string,mixed> $options */
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
        $apiResponse = $this->apiResponse($response);

        if ($saveCard) {
            $vaultId = $apiResponse['data']['customer_id'] ?? null;

            if ($vaultId) {
                $customerDetails = $this->getCustomerProfile($vaultId, $context);
                $this->payTraceCustomerVaultService->storeCardFromCustomerDetails(
                    $vaultId,
                    $billingData['fullName'],
                    $customerDetails,
                    $context
                );
            }
        }

        return $apiResponse;
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $billingData
     * @return array<string,mixed>
     * @throws \Exception
     */
    public function processEcheckDeposit(array $data, array $billingData, string $salesChannelId): array
    {
        $baseUrl = $this->getModeUrl($salesChannelId);
        /** @var array{method:string,url:string} $fullEndpointUrl */
        $fullEndpointUrl = Endpoints::getUrl($baseUrl, Endpoints::ACH_DEPOSIT);

        /** @var array<string,mixed> $options */
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
        return $this->apiResponse($response);
    }

    /**
     * @param array<string,mixed> $paymentData
     * @param array<string,mixed> $billingData
     * @return array<string,mixed>
     */
    public function processOrderFirstAchPayment(array $paymentData, array $billingData, string $salesChannelId,): array
    {
        try {
            $apiResponse = $this->processEcheckDeposit($paymentData, $billingData, $salesChannelId);
        } catch (\Exception $e) {
            $this->logger->error('Vaulted payment processing failed: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Payment processing failed due to an internal error.',
                'transactionId' => null,
            ];
        }

        $success = !($apiResponse['error'] ?? true);

        return [
            'success' => $success,
            'message' => $apiResponse['message'] ?? ($success ? 'Payment processed successfully.' : 'ACH payment failed'),
            'transactionId' => $apiResponse['data']['transaction_id'] ?? null,
        ];
    }

    /**
     * @param array<string,mixed> $token
     * @param array<string,mixed> $billingData
     * @return ResponseInterface|array<string,mixed>
     * @throws \Exception
     */
    public function processPaymentAuthorize(array $token, string $amount, array $billingData, bool $saveCard, SalesChannelContext $context): ResponseInterface|array
    {
        $baseUrl = $this->getModeUrl($context->getSalesChannelId());
        /** @var array{method:string,url:string} $fullEndpointUrl */
        $fullEndpointUrl = Endpoints::getUrl($baseUrl, Endpoints::AUTHORIZE);

        /** @var array<string,mixed> $options */
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
        $apiResponse = $this->apiResponse($response);

        if ($saveCard) {
            $transactionId = $apiResponse['data']['transaction_id'] ?? null;
            if ($transactionId) {
                $customerCreateResponse = $this->createCustomerByTransaction($transactionId, $billingData, $context);

                if (!($customerCreateResponse['error'] ?? true)) {
                    $vaultId = $customerCreateResponse['data']['customer_id'];

                    $customerDetails = $this->getCustomerProfile($vaultId, $context);

                    $this->payTraceCustomerVaultService->storeCardFromCustomerDetails(
                        $vaultId,
                        $billingData['fullName'],
                        $customerDetails,
                        $context
                    );
                }
            }
        }

        return $apiResponse;
    }

    /**
     * @param array<string,mixed> $data
     * @return ResponseInterface|array<string,mixed>
     * @throws \Exception
     */
    public function processCapture(array $data, string $salesChannelId): ResponseInterface|array
    {
        $baseUrl = $this->getModeUrl($salesChannelId);
        /** @var array{method:string,url:string} $fullEndpointUrl */
        $fullEndpointUrl = Endpoints::getUrl($baseUrl, Endpoints::CAPTURE);

        /** @var array<string,mixed> $options */
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
        return $this->apiResponse($response);
    }

    /**
     * @param array<string,mixed> $data
     * @return string|array<string,mixed>
     * @throws \Exception
     */
    public function captureRefund(array $data, string $salesChannelId): string|array
    {
        $baseUrl = $this->getModeUrl($salesChannelId);
        /** @var array{method:string,url:string} $fullEndpointUrl */
        $fullEndpointUrl = Endpoints::getUrl($baseUrl, Endpoints::REFUND);

        /** @var array<string,mixed> $options */
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
        return $this->apiResponse($response);
    }

    /**
     * @param array<string,mixed> $data
     * @return string|array<string,mixed>
     * @throws \Exception
     */
    public function voidTransaction(array $data, string $salesChannelId): string|array
    {
        $baseUrl = $this->getModeUrl($salesChannelId);
        /** @var array{method:string,url:string} $fullEndpointUrl */
        $fullEndpointUrl = Endpoints::getUrl($baseUrl, Endpoints::VOID);

        /** @var array<string,mixed> $options */
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
                        'custom_dba' => 'Void transaction: ' . $data['transactionId'],
                    ]
                ]
            ]),
        ];

        $response = $this->request($fullEndpointUrl, $options);
        return $this->apiResponse($response);
    }

    /**
     * @param array<string,mixed> $data
     * @return ResponseInterface|array<string,mixed>
     * @throws \Exception
     */
    public function processVaultedPayment(array $data, SalesChannelContext $context): ResponseInterface|array
    {
        if (empty($data['selectedCardVaultedId'])) {
            throw  new PaymentException(400, PaymentException::PAYMENT_REFUND_UNKNOWN_ERROR, 'Missing customer ID');
        }

        /** @var string|null $authAndCapture */
        $authAndCapture = $this->payTraceConfigService->getConfig('authorizeAndCapture');

        /** @var array<string,string> $endpointMap */
        $endpointMap = [
            'auth' => Endpoints::AUTHORIZE_VAULTED,
            'capture' => Endpoints::VAULTED_TRANSACTION,
        ];

        $endpoint = $endpointMap[$authAndCapture] ?? Endpoints::VAULTED_TRANSACTION;

        /** @var array{method:string,url:string} $endpointDetails */
        $endpointDetails = Endpoints::getUrlDynamicParam(
            $this->getModeUrl($context->getSalesChannelId()),
            $endpoint,
            [$data['selectedCardVaultedId']]
        );

        /** @var array<string,mixed> $options */
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
        return $this->apiResponse($response);
    }

    /**
     * @param array<string,mixed> $data
     * @return ResponseInterface|array<string,mixed>
     * @throws \Exception
     */
    public function createCustomerProfile(array $data, SalesChannelContext $context): ResponseInterface|array
    {
        $baseUrl = $this->getModeUrl($context->getSalesChannelId());
        /** @var array{method:string,url:string} $fullEndpointUrl */
        $fullEndpointUrl = Endpoints::getUrl($baseUrl, Endpoints::ADD_CARD);

        /** @var array<string,mixed> $options */
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
        return $this->apiResponse($response);
    }

    /**
     * @return array<string,mixed>
     * @throws \Exception
     */
    public function getCustomerProfile(string $vaultedCustomerId, SalesChannelContext $context): array
    {
        /** @var array{method:string,url:string} $endpoint */
        $endpoint = Endpoints::getUrlDynamicParam(
            $this->getModeUrl($context->getSalesChannelId()),
            Endpoints::CUSTOMER_PROFILE,
            [$vaultedCustomerId]
        );

        /** @var array<string,mixed> $options */
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getAuthorizationToken($context->getSalesChannelId()),
                'X-Integrator-Id' => '9999Shopware',
                'X-Permalinks' => true,
                'Content-Type' => 'application/json',
            ]
        ];

        $response = $this->request($endpoint, $options);
        return $this->apiResponse($response);
    }

    /**
     * @return ResponseInterface|array<string,mixed>
     * @throws \Exception
     */
    public function deleteVaultedCard(string $vaultedCustomerId, SalesChannelContext $context): ResponseInterface|array
    {
        /** @var array{method:string,url:string} $endpointDetails */
        $endpointDetails = Endpoints::getUrlDynamicParam(
            $this->getModeUrl($context->getSalesChannelId()),
            Endpoints::DELETE_CARD,
            [$vaultedCustomerId],
        );

        /** @var array<string,mixed> $options */
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
            /** @var array<string,mixed>|null $decodedResponse */
            $decodedResponse = json_decode($responseBody, true);

            if (isset($decodedResponse['error']) && $decodedResponse['error']) {
                throw new \Exception($decodedResponse['message'] ?? 'Unknown error');
            }

            return [
                'error' => false,
                'message' => 'Success',
                'data' => $decodedResponse['data'] ?? [],
            ];
        }

        throw new \Exception('Invalid response received');
    }

    /**
     * @return array<string,string>
     */
    private function buildRequestBody(?string $salesChannelId = null): array
    {
        $mode = $this->payTraceConfigService->getMode(
            (string) $this->payTraceConfigService->getConfig('mode', $salesChannelId)
        );

        $isLive = $mode === 'live';

        return [
            'grant_type'    => 'client_credentials',
            'client_id'     => (string) $this->payTraceConfigService->getConfig(
                $isLive ? 'clientIdProd' : 'clientIdSandbox',
                $salesChannelId
            ),
            'client_secret' => (string) $this->payTraceConfigService->getConfig(
                $isLive ? 'clientSecretProd' : 'clientSecretSandbox',
                $salesChannelId
            ),
        ];
    }

    /**
     * @param ResponseInterface|array<string,mixed> $response
     * @return array<string,mixed>
     */
    private function apiResponse($response): array
    {
        if (is_array($response)) {
            /** @var array<string,mixed> $decodedResponse */
            $decodedResponse = $response;
        } elseif ($response instanceof ResponseInterface) {
            $responseBody = $response->getBody()->getContents();
            /** @var array<string,mixed>|null $decodedResponse */
            $decodedResponse = json_decode($responseBody, true);
        } else {
            throw new PaymentException(400, PaymentException::PAYMENT_PROCESS_ERROR, 'Invalid response type');
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
            throw new PaymentException(
                400,
                PaymentException::PAYMENT_PROCESS_ERROR,
                $decodedResponse['message']['data'][0]['detail'] ?? 'Unknown error'
            );
        }

        if (isset($decodedResponse['data']['response_code'])) {
            /** @var array<int> $errorCodes */
            $errorCodes = [1, 102, 103, 107, 110, 113, 125, 167];
            if (in_array($decodedResponse['data']['response_code'], $errorCodes, true)) {
                throw new PaymentException(400, PaymentException::PAYMENT_PROCESS_ERROR, $decodedResponse['data']['message']);
            }
        }

        return [
            'error' => false,
            'message' => 'Success',
            'data' => $decodedResponse['data'] ?? null,
        ];
    }

    /**
     * @param array{method:string,url:string} $endpoint
     * @param array<string,mixed> $options
     * @return ResponseInterface|array<string,mixed>
     * @throws \Exception
     */
    private function request(array $endpoint, array $options): ResponseInterface|array
    {
        try {
            ['method' => $method, 'url' => $url] = $endpoint;
            return $this->client->request($method, $url, $options);
        } catch (GuzzleException $e) {
            return $this->handleError($e);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function handleError(GuzzleException $e): array
    {
        if ($e instanceof RequestException && $e->hasResponse()) {
            $response = $e->getResponse();

            if ($response instanceof ResponseInterface) {
                $responseBody = $response->getBody()->getContents();
                /** @var array<string,mixed>|string|null $decodedBody */
                $decodedBody = json_decode($responseBody, true);

                return [
                    'error' => true,
                    'code' => $e->getCode(),
                    'message' => is_array($decodedBody) ? ($decodedBody['message'] ?? $decodedBody) : $decodedBody,
                ];
            }
        }

        return [
            'error' => true,
            'code' => $e->getCode(),
            'message' => $e->getMessage(),
        ];
    }

    /**
     * @param array<string,mixed> $billingData
     * @return array<string,mixed>
     * @throws \Exception
     */
    public function createCustomerByTransaction(string $transactionId, array $billingData, SalesChannelContext $context): array
    {
        $customerId = $context->getCustomer()?->getId();
        if (!$customerId) {
            throw new CustomerException(404, CustomerException::CUSTOMER_NOT_FOUND, 'Customer not found');
        }

        $baseUrl = $this->getModeUrl($context->getSalesChannelId());
        /** @var array{method:string,url:string} $url */
        $url = Endpoints::getUrl($baseUrl, Endpoints::CREATE_CUSTOMER_BY_TRANSACTION);

        $customerLabel = $this->payTraceCustomerVaultService->getNextCardLabel($context);

        /** @var array<string,mixed> $body */
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

        /** @var array<string,mixed> $options */
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getAuthorizationToken($context->getSalesChannelId()),
                'X-Integrator-Id' => '9999Shopware',
                'X-Permalinks' => true,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($body),
        ];

        return $this->apiResponse($this->request($url, $options));
    }

    public function testConnection(string $salesChannelId): bool
    {
        try {
            $mode = $this->payTraceConfigService->getConfig('mode', $salesChannelId);
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

            /** @var array<string,string> $body */
            $body = [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ];

            /** @var array<string,mixed> $options */
            $options = [
                'form_params' => $body,
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            ];

            /** @var array{method:string,url:string} $endpoint */
            $endpoint = [
                'method' => 'POST',
                'url' => $baseUrl . '/v3/token',
            ];

            $response = $this->request($endpoint, $options);

            // Guard the union: ResponseInterface|array
            if ($response instanceof ResponseInterface && $response->getStatusCode() === 201) {
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
        $mode = $this->payTraceConfigService->getConfig('mode', $salesChannelId);
        if ($mode === 'live') {
            return EnvironmentUrl::PRODUCTION;
        }
        return EnvironmentUrl::SANDBOX;
    }
}
