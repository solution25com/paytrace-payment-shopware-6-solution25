<?php

declare(strict_types=1);

namespace solu1Paytrace\Storefront\Controller;

use solu1Paytrace\Service\PayTraceApiService;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class TestApiConnectionController extends StorefrontController
{
    private PayTraceApiService $paytraceApiService;


    public function __construct(PayTraceApiService $paytraceApiService)
    {
        $this->paytraceApiService = $paytraceApiService;
    }


    #[Route(path: '/api/_action/paytrace-test-connection/test-connection', name: 'api.action.paytrace.test-connection', methods: ['POST'])]
    public function testConnection(Request $request, Context $context): Response
    {
        $salesChannelId = $request->get('salesChannelId') ?? '';
        $result = $this->paytraceApiService->testConnection($salesChannelId);

        return new JsonResponse(['success' => $result]);
    }
}
