<?php declare(strict_types=1);

namespace Shopware\Core\Content\DependencyInjection;

use Shopware\Core\Content\Cms\Service\CmsFormSlotConfigResolver;
use Shopware\Core\Content\ContactForm\SalesChannel\ContactFormRoute;
use Shopware\Core\Content\ContactForm\Validation\ContactFormValidationFactory;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\RequestStack;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(ContactFormValidationFactory::class)
        ->args([
            service('event_dispatcher'),
            service(SystemConfigService::class),
        ]);

    $services->set(ContactFormRoute::class)
        ->public()
        ->args([
            service(ContactFormValidationFactory::class),
            service(DataValidator::class),
            service('event_dispatcher'),
            service('salutation.repository'),
            service(RequestStack::class),
            service('shopware.rate_limiter'),
            service(CmsFormSlotConfigResolver::class),
        ]);
};
