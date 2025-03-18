<?php

namespace PayTrace\Service;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use PayTrace\Library\Constants\Endpoints;
use PayTrace\Library\Constants\EnvironmentUrl;
use Psr\Http\Message\ResponseInterface;

class PayTraceApiService extends Endpoints
{
  private PayTraceConfigService $payTraceConfigService;
  private Client $client;

  public function __construct(PayTraceConfigService $payTraceConfigService)
  {
    $this->payTraceConfigService = $payTraceConfigService;
    $this->client = new Client();
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

  private function getAuthorizationToken(): string
  {
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

      return $decodedBody['data']['access_token'] ?? 'Unknown error';
    }

    return "Error: Invalid response received";
  }

  public function generatePaymentToken(): string|array
  {
    $fullEndpointUrl = Endpoints::getUrl(Endpoints::PAYMENT_FIELD_TOKENS);

    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->getAuthorizationToken(),
        'X-Integrator-Id' => $this->payTraceConfigService->getConfig('IntegratorId'),
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

  public function createCustomerProfile(array $data): ResponseInterface|array
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
          'name' => 'Edon Sedon',
          'street_address' => '123 Main St',
          'street_address2' => 'Apt 4B',
          'city' => 'Sample City',
          'state' => 'CA',
          'postal_code' => '90001',
          'country' => 'USA',
        ],
        'customer_label' => 'EdonTestUser'
      ]),
    ];


    $response = $this->request($fullEndpointUrl, $options);

    if ($response instanceof Response) {
      $responseBody = $response->getBody()->getContents();
      return json_decode($responseBody, true);
    }

    return ['error' => true, 'message' => 'Invalid response received'];

  }

  private function buildRequestBody(): array
  {
    return [
      'grant_type' => 'client_credentials',
      'client_id' => $this->payTraceConfigService->getConfig('clientIdSandbox'),
      'client_secret' => $this->payTraceConfigService->getConfig('clientSecretSandbox'),
    ];
  }
}
