<?php declare(strict_types=1);

namespace Shopware\Core\Content\DependencyInjection;

use Shopware\Core\Content\LegalGuaranteeNotice\LegalGuaranteeNoticeRenderer;
use Shopware\Core\Content\LegalGuaranteeNotice\LegalGuaranteeNoticeTwigFilter;
use Shopware\Core\Content\LegalGuaranteeNotice\SalesChannel\AbstractLegalGuaranteeNoticeRoute;
use Shopware\Core\Content\LegalGuaranteeNotice\SalesChannel\LegalGuaranteeNoticeRoute;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(LegalGuaranteeNoticeRenderer::class)
        ->args([
            service('twig'),
            service(LanguageLocaleCodeProvider::class),
        ]);

    $services->set(LegalGuaranteeNoticeTwigFilter::class)
        ->args([
            service(LegalGuaranteeNoticeRenderer::class),
        ])
        ->tag('twig.extension');

    $services->set(LegalGuaranteeNoticeRoute::class)
        ->public()
        ->args([
            service(SystemConfigService::class),
            service(LegalGuaranteeNoticeRenderer::class),
        ]);

    $services->alias(AbstractLegalGuaranteeNoticeRoute::class, LegalGuaranteeNoticeRoute::class);
};
