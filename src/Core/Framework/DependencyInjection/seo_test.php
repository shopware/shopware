<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Shopware\Core\Content\Test\Seo\Twig\LastLetterBigTwigFilter;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(LastLetterBigTwigFilter::class)
        ->tag('shopware.seo_url.twig.extension');
};
