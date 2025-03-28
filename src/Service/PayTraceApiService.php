<?php

namespace PayTrace\Service;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use PayTrace\Library\Constants\Endpoints;
use Psr\Http\Message\ResponseInterface;

class PayTraceApiService extends Endpoints
{
  private PayTraceConfigService $payTraceConfigService;
  private Client $client;
  private ?string $authToken = null;
  private ?int $authTokenExpiryTime = null;

  public function __construct(PayTraceConfigService $payTraceConfigService)
  {
    $this->payTraceConfigService = $payTraceConfigService;
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

    $res = $this->request($fullEndpointUrl, $options);

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
        'X-Integrator-Id' => $this->payTraceConfigService->getConfig('integratorId'),
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

  public function captureRefund(array $body): string|array
  {
    $fullEndpointUrl = Endpoints::getUrl(Endpoints::REFUND);

    $options = [
      'json' => $body,
      'headers' => [
        'Authorization' => 'Bearer ' . $this->getAuthorizationToken(),
        'X-Integrator-Id' => $this->payTraceConfigService->getConfig('integratorId'),
      ],
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

  public function processPayment(array $data, string $amount): ResponseInterface|array
  {
    $fullEndpointUrl = Endpoints::getUrl(Endpoints::TRANSACTION);

    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->getAuthorizationToken(),
        'X-Integrator-Id' => $this->payTraceConfigService->getConfig('integratorId'),
        'X-Permalinks' => true,
        'Content-Type' => 'application/json',
      ],
      'body' => json_encode([
        'hpf_token' => $data['hpf_token'],
        'enc_key' => $data['enc_key'],
        'amount' => $amount,
        'merchant_id' => $this->payTraceConfigService->getConfig('merchantId'),
      ]),
    ];

    $response = $this->request($fullEndpointUrl, $options);

    if ($response instanceof Response) {
      $responseBody = $response->getBody()->getContents();
      return json_decode($responseBody, true);
    }

    return ['error' => true, 'message' => 'Invalid response received'];
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
        'X-Integrator-Id' => $this->payTraceConfigService->getConfig('integratorId'),
        'X-Permalinks' => true,
        'Content-Type' => 'application/json',
      ],
      'body' => json_encode([
        'amount' => $data['amount'],
        'merchant_id' => $this->payTraceConfigService->getConfig('merchantId'),
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
        'X-Integrator-Id' => $this->payTraceConfigService->getConfig('integratorId'),
        'X-Permalinks' => true,
        'Content-Type' => 'application/json',
      ],
      'body' => json_encode([
        'hpf_token' => $data['cardToken']['hpf_token'],
        'enc_key' => $data['cardToken']['enc_key'],
        'merchant_id' => $this->payTraceConfigService->getConfig('merchantId'),
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

    $response = $this->request($fullEndpointUrl, $options);

    return $this->ApiResponse($response);
  }


  private function buildRequestBody(): array
  {
    return [
      'grant_type' => 'client_credentials',
      'client_id' => $this->payTraceConfigService->getConfig('clientIdSandbox'),
      'client_secret' => $this->payTraceConfigService->getConfig('clientSecretSandbox'),
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
