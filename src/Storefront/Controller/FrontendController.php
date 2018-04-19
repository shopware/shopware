<?php

namespace Shopware\Storefront\Controller;

use Symfony\Component\HttpFoundation\Response;

abstract class FrontendController extends Controller
{
    protected function render($view, array $parameters = [], Response $response = null): Response
    {
        $context = $this->get('shopware.storefront.context.storefront_context_service')
            ->getShopContext();

        $navigationId = $this->get('request_stack')->getCurrentRequest()->attributes->get('active_category_id');

        $parameters['navigation'] = $this->get('shopware.storefront.navigation.navigation_loader')
            ->load($navigationId, $context);

        return parent::render($view, $parameters, $response);
    }
}