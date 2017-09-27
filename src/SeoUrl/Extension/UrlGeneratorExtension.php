<?php

namespace Shopware\SeoUrl\Extension;

use Shopware\SeoUrl\Event\SeoUrlBasicLoadedEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;

class UrlGeneratorExtension extends SeoUrlExtension
{
    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function seoUrlBasicLoaded(SeoUrlBasicLoadedEvent $event): void
    {
        parent::seoUrlBasicLoaded($event);

        if (!$this->container->has('router')) {
            return;
        }

        $router = $this->container->get('router');
        if (!$router->getContext()) {
            return;
        }

        $context = $router->getContext();

        foreach ($event->getSeoUrls() as $seoUrl) {
            $url = implode('/', array_filter([
                trim($context->getBaseUrl(), '/'),
                trim($seoUrl->getSeoPathInfo(), '/'),
            ]));

            $url = sprintf('%s://%s/%s', $context->getScheme(), $context->getHost(), $url);
            $seoUrl->setUrl($url);
        }
    }
}
