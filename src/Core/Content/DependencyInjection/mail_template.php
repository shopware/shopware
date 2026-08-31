<?php declare(strict_types=1);

namespace Shopware\Core\Content\DependencyInjection;

use Psr\Clock\ClockInterface;
use Shopware\Core\Content\Mail\Payload\MailPayloadFactory;
use Shopware\Core\Content\Mail\Service\MailService;
use Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooterTranslation\MailHeaderFooterTranslationDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateMedia\MailTemplateMediaDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateTranslation\MailTemplateTranslationDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateTypeTranslation\MailTemplateTypeTranslationDefinition;
use Shopware\Core\Content\MailTemplate\Api\MailActionController;
use Shopware\Core\Content\MailTemplate\MailTemplateDefinition;
use Shopware\Core\Content\MailTemplate\Request\Resolver\GetDataAndSendRequestResolver;
use Shopware\Core\Content\MailTemplate\Request\Resolver\PreviewRequestResolver;
use Shopware\Core\Content\MailTemplate\Request\Resolver\SimulateRequestResolver;
use Shopware\Core\Content\MailTemplate\Service\MailDataProvider;
use Shopware\Core\Content\MailTemplate\Service\MailDataSimulator;
use Shopware\Core\Content\MailTemplate\Service\MailTemplateContentBuilder;
use Shopware\Core\Content\MailTemplate\Service\MailTemplateSendService;
use Shopware\Core\Content\MailTemplate\Service\MailTemplateService;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\SalesChannelProvider;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    // Template Entities
    $services->set(MailTemplateDefinition::class)
        ->tag('shopware.entity.definition', ['entity' => 'mail_template']);

    $services->set(MailTemplateTranslationDefinition::class)
        ->tag('shopware.entity.definition', ['entity' => 'mail_template_translation']);

    $services->set(MailTemplateTypeDefinition::class)
        ->tag('shopware.entity.definition', ['entity' => 'mail_template_type']);

    $services->set(MailTemplateTypeTranslationDefinition::class)
        ->tag('shopware.entity.definition', ['entity' => 'mail_template_type_translation']);

    $services->set(MailTemplateMediaDefinition::class)
        ->tag('shopware.entity.definition');

    // Header Footer Entities
    $services->set(MailHeaderFooterDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(MailHeaderFooterTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    // Controller
    $services->set(MailActionController::class)
        ->public()
        ->args([
            service(StringTemplateRenderer::class),
            service(MailTemplateService::class),
            service(MailTemplateSendService::class),
            service(MailPayloadFactory::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(MailDataProvider::class)
        ->args([
            tagged_iterator('shopware.mail.data_provider', 'key'),
        ]);

    $services->set(MailTemplateService::class)
        ->args([
            service('mail_template.repository'),
            service(StringTemplateRenderer::class),
            service(MailDataProvider::class),
            service(MailDataSimulator::class),
            service(MailTemplateContentBuilder::class),
            service('event_dispatcher'),
        ]);

    $services->set(MailTemplateSendService::class)
        ->args([
            service(MailService::class),
            service(MailDataProvider::class),
        ]);

    $services->set(MailTemplateContentBuilder::class);

    $services->set(MailDataSimulator::class)
        ->args([
            service(BusinessEventCollector::class),
            service(DefinitionInstanceRegistry::class),
            service('event_dispatcher'),
            tagged_iterator('shopware.mail.data_provider', 'key'),
            service(ClockInterface::class),
        ]);

    $services->set(PreviewRequestResolver::class)
        ->args([
            service(MailTemplateService::class),
            service(SalesChannelProvider::class),
        ])
        ->tag('controller.argument_value_resolver');

    $services->set(GetDataAndSendRequestResolver::class)
        ->args([
            service(MailTemplateService::class),
            service(MailPayloadFactory::class),
        ])
        ->tag('controller.argument_value_resolver');

    $services->set(SimulateRequestResolver::class)
        ->args([
            service(SalesChannelProvider::class),
        ])
        ->tag('controller.argument_value_resolver');
};
