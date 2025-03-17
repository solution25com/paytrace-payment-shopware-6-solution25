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
  private string $bearerToken;

  public function __construct(PayTraceConfigService $payTraceConfigService)
  {
    $this->payTraceConfigService = $payTraceConfigService;
    $this->client = new Client();
  }

//  public function setupClient(string $salesChannelId = ''): void
//  {
//    if (empty($this->bearerToken)) {
//      $mode = $this->payTraceConfigService->getConfig('mode', $salesChannelId);
//      $isLive = $mode === 'live';
//
//      $baseUrl = $isLive ? EnvironmentUrl::PRODUCTION : EnvironmentUrl::SANDBOX;
//      $clientKey = $this->payTraceConfigService->getConfig($isLive ? 'clientIdProd' : 'clientIdSandbox', $salesChannelId);
//      $clientSecret = $this->payTraceConfigService->getConfig($isLive ? 'clientSecretProd' : 'clientSecretSandbox', $salesChannelId);
//
//      $this->client = new Client(['base_uri' => $baseUrl->value]);
//
//      $this->bearerToken = $this->getAuthorizationToken($clientKey, $clientSecret);
//    }
//  }

  private function getAuthorizationToken(): string
  {
    $fullEndpointUrl = Endpoints::getUrl(Endpoints::AUTH_TOKEN);
    $body = $this->buildRequestBody();

    try {
      $options = [
        'form_params' => $body,
        'headers' => [
          'Content-Type' => 'application/x-www-form-urlencoded',
        ]
      ];

      $res = $this->request($fullEndpointUrl, $options);

      if ($res instanceof Response) {
        $responseBody = $res->getBody()->getContents();
        $decodedBody = json_decode($responseBody, true);

        if (isset($decodedBody['error']) && $decodedBody['error']) {
          return "Error: " . $decodedBody['message'] ?? 'Unknown error';
        }

        return $decodedBody['data']['access_token'] ;
      }

      return "Error: Invalid response received";

    } catch (GuzzleException $e) {
      return 'Error occurred: ' . $e->getMessage();
    }
  }

  private function request(array $endpoint, array $options): ResponseInterface|array
  {
    try {
      ['method' => $method, 'url' => $url] = $endpoint;
      return $this->client->request($method, $url, $options);
    } catch (GuzzleException $e) {
      if ($e->hasResponse()) {
        $responseBody = $e->getResponse()->getBody()->getContents();
        $decodedBody = json_decode($responseBody, true);
        return [
          'error' => true,
          'code' => $e->getCode(),
          'message' => $decodedBody['message'] ?? $decodedBody,
        ];
      } else {
        return [
          'error' => true,
          'code' => $e->getCode(),
          'message' => $e->getMessage(),
        ];
      }
    }
  }

  public function generatePaymentToken(): string|array
  {
    $fullEndpointUrl = Endpoints::getUrl(Endpoints::PAYMENT_FIELD_TOKENS);

    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->getAuthorizationToken(),
        'X-Integrator-Id' => $this->payTraceConfigService->getConfig('integrator'),
        'Content-Type' => 'application/json',
      ],
    ];

    try {
      $response = $this->request($fullEndpointUrl, $options);

      if ($response instanceof Response) {
        $responseBody = $response->getBody()->getContents();
        $dec = json_decode($responseBody, true);
        return $dec['data']['clientKey'];
      }

      return [
        'error' => true,
        'message' => 'Invalid response received'
      ];

    } catch (GuzzleException $e) {
      return [
        'error' => true,
        'code' => $e->getCode(),
        'message' => $e->getMessage(),
      ];
    }
  }

  public function captureRefund(array $body): string|array
  {
    $fullEndpointUrl = Endpoints::getUrl(Endpoints::REFUND);

    try {
      $options = [
        'json' => $body,
        'headers' => [
          'Authorization' => 'Bearer ' . $this->getAuthorizationToken(),
          'X-Integrator-Id' => '9999Shopware',
        ],
      ];

      $response = $this->request($fullEndpointUrl, $options);

      if ($response instanceof Response) {
        $responseBody = $response->getBody()->getContents();
        $dec = json_decode($responseBody, true);

        if (isset($dec['data'])) {
          return $dec['data'];
        } else {
          throw new Exception('No data returned from API');
        }
      }
    } catch (GuzzleException $e) {
      return ['error' => 'Network or request error occurred'];
    } catch (Exception $e) {
      return ['error' => $e->getMessage()];
    }

    return ['error' => 'Unexpected error occurred'];
  }

  private function buildRequestBody(): array
  {
    return [
      'grant_type' => 'client_credentials',
      'client_id' => $this->payTraceConfigService->getConfig('clientId'),
      'client_secret' => $this->payTraceConfigService->getConfig('clientSecret')
    ];
  }

  public function processPayment(array $data, string $amount): ResponseInterface|array
  {
    $fullEndpointUrl = Endpoints::getUrl(Endpoints::TRANSACTION);

    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->getAuthorizationToken(),
        'X-Integrator-Id' => $this->payTraceConfigService->getConfig('integrator'),
        'X-Permalinks' => true,
        'Content-Type' => 'application/json',
      ],
      'body' => json_encode([
        'hpf_token' => $data['hpf_token'],
        'enc_key' => $data['enc_key'],
        'amount' => $amount,
        'merchant_id' => $this->payTraceConfigService->getConfig('merchantId')
      ])
    ];

    try {
      $response = $this->request($fullEndpointUrl, $options);

      if ($response instanceof Response) {
        $responseBody = $response->getBody()->getContents();
        $dec = json_decode($responseBody, true);
        return $dec;
      }

      return [
        'error' => true,
        'message' => 'Invalid response received'
      ];

    } catch (GuzzleException $e) {
      return [
        'error' => true,
        'code' => $e->getCode(),
        'message' => $e->getMessage(),
      ];
    }

  }
}
