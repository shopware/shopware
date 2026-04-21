<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DependencyInjection;

use Shopware\Core\Checkout\Document\Renderer\DocumentRendererRegistry;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentNumberGenerator;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentDependencyResolver;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerator;
use Shopware\Core\Checkout\DocumentV2\Provider\DocumentDataProviderRegistry;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGenerator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(DocumentNumberGenerator::class)
        ->args([
            NumberRangeValueGenerator::class,
        ]);

    // $services->set(InvoiceDataProvider::class)
    //    ->args([...])
    //    ->tag('shopware.documentV2.provider');

    $services->set(DocumentDataProviderRegistry::class)
        ->args([
            tagged_iterator('shopware.documentV2.provider'),
        ]);

    // $services->set(HtmlRenderer::class)
    //    ->args([...])
    //    ->tag('shopware.documentV2.renderer');

    $services->set(DocumentRendererRegistry::class)
        ->args([
            tagged_iterator('shopware.documentV2.renderer'),
        ]);

    $services->set(DocumentDependencyResolver::class)
        ->args([
            DocumentRendererRegistry::class,
        ]);

    $services->set(DocumentGenerator::class)
        ->args([
            DocumentDataProviderRegistry::class,
            DocumentRendererRegistry::class,
            DocumentNumberGenerator::class,
            service('order.repository'),
        ]);
};
