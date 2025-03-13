<?php declare(strict_types=1);

namespace PayTrace\Storefront\Controller;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class ExampleController extends StorefrontController
{
    #[Route(
        path: '/example',
        name: 'frontend.example.example',
        methods: ['GET']
    )]
    public function showExample(Request $request, SalesChannelContext $context): Response
    {
        return $this->renderStorefront('@PayTrace/storefront/page/example.html.twig', [
            'example' => 'Hello world'
        ]);
    }
}
