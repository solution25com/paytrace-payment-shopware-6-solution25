<?php

declare(strict_types=1);

namespace solu1Paytrace\Library\Constants;

abstract class Endpoints
{
    protected const PAYMENT_FIELD_TOKENS = 'PAYMENT_FIELD_TOKENS';
    protected const TRANSACTION = 'TRANSACTION';
    protected const CUSTOMER_TRANSACTION = 'CUSTOMER_TRANSACTION';
    protected const CREATE_CUSTOMER_BY_TRANSACTION = 'CREATE_CUSTOMER_BY_TRANSACTION';
    protected const AUTHORIZE = 'AUTHORIZE';
    protected const CAPTURE = 'CAPTURE';
    protected const VOID = 'VOID';
    protected const REFUND = 'REFUND';
    protected const VAULTED_TRANSACTION = 'VAULTED_TRANSACTION';
    protected const AUTH_TOKEN = 'AUTH_TOKEN';
    protected const ADD_CARD = 'ADD_CUSTOMER_CARD';
    protected const DELETE_CARD = 'DELETE_CUSTOMER_CARD';
    protected const ACH_DEPOSIT = 'ACH_DEPOSIT';
    protected const CUSTOMER_PROFILE = 'CUSTOMER_PROFILE';
    protected const AUTHORIZE_VAULTED = 'AUTHORIZE_VAULTED';

    /**
     * @var array<string, array{method: 'GET'|'POST'|'DELETE', url: string}>
     */
    private static array $endpoints = [
        self::PAYMENT_FIELD_TOKENS => [
            'method' => 'POST',
            'url' => '/v3/payment-fields/token'
        ],
        self::TRANSACTION => [
            'method' => 'POST',
            'url' => '/v3/card/sale/pt-protect'
        ],
        self::AUTHORIZE => [
            'method' => 'POST',
            'url' => '/v3/card/authorize/pt-protect'
        ],
        self::CAPTURE => [
            'method' => 'POST',
            'url' => '/v3/card/batch/capture'
        ],
        self::AUTH_TOKEN => [
            'method' => 'POST',
            'url' => '/v3/token'
        ],
        self::REFUND => [
            'method' => 'POST',
            'url' => '/v3/card/batch/refund'
        ],
        self::ADD_CARD => [
            'method' => 'POST',
            'url' => '/v3/customer/create/pt-protect'
        ],
        self::VAULTED_TRANSACTION => [
            'method' => 'POST',
            'url' => '/v3/card/sale/customer/'
        ],
        self::VOID => [
            'method' => 'POST',
            'url' => '/v3/card/batch/void'
        ],
        self::DELETE_CARD => [
            'method' => 'DELETE',
            'url' => '/v3/customer/'
        ],
        self::ACH_DEPOSIT => [
            'method' => 'POST',
            'url' => '/v3/checks/payment'
        ],
        self::CUSTOMER_PROFILE => [
            'method' => 'GET',
            'url' => '/v3/customer/'
        ],
        self::CUSTOMER_TRANSACTION => [
            'method' => 'POST',
            'url' => '/v3/card/sale/pt-protect/customer/'
        ],
        self::CREATE_CUSTOMER_BY_TRANSACTION => [
            'method' => 'POST',
            'url' => '/v3/customer/create/by-transaction'
        ],
        self::AUTHORIZE_VAULTED => [
            'method' => 'POST',
            'url' => '/v3/card/authorize/customer/'
        ],
    ];

    /**
     * @return array{method: 'GET'|'POST'|'DELETE', url: string}
     */
    protected static function getEndpoint(string $endpoint): array
    {
        return self::$endpoints[$endpoint];
    }

    /**
     * @return array{method: 'GET'|'POST'|'DELETE', url: string}
     */
    public static function getUrl(string $baseUrl, string $endpoint): array
    {
        $endpointDetails = self::getEndpoint($endpoint);
        return [
            'method' => $endpointDetails['method'],
            'url' => $baseUrl . $endpointDetails['url'],
        ];
    }

    /**
     * @param array<int, string> $params
     * @param array<string, mixed> $queryParam
     * @return array{method: 'GET'|'POST'|'DELETE', url: string}
     */
    public static function getUrlDynamicParam(
        string $modeUrl,
        string $endpoint,
        array $params = [],
        array $queryParam = []
    ): array {
        $endpointDetails = self::getEndpoint($endpoint);
        $baseUrl = $endpointDetails['url'];

        $paramBuilder = implode('', $params);
        $queryString = !empty($queryParam) ? '?' . http_build_query($queryParam) : '';

        return [
            'method' => $endpointDetails['method'],
            'url' => $modeUrl . $baseUrl . $paramBuilder . $queryString,
        ];
    }
}
