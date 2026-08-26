<?php declare(strict_types=1);

namespace Shopware\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Content\Mail\Message\SendMailHandler;
use Shopware\Core\Content\Mail\Payload\MailPayloadFactory;
use Shopware\Core\Content\Mail\Service\MailAttachmentsBuilder;
use Shopware\Core\Content\Mail\Service\MailFactory;
use Shopware\Core\Content\Mail\Service\MailSender;
use Shopware\Core\Content\Mail\Service\MailService;
use Shopware\Core\Content\Mail\Service\SendMailTemplate;
use Shopware\Core\Content\Mail\Subscriber\FailedMessageSubscriber;
use Shopware\Core\Content\Mail\Telemetry\MailGroupResolver;
use Shopware\Core\Content\Mail\Telemetry\MailMetricsInstrumentor;
use Shopware\Core\Content\Mail\Transport\MailerTransportLoader;
use Shopware\Core\Content\Mail\Transport\SmtpOauthAuthenticator;
use Shopware\Core\Content\Mail\Transport\SmtpOauthTokenProvider;
use Shopware\Core\Content\Mail\Transport\SmtpOauthTransportFactoryDecorator;
use Shopware\Core\Content\MailTemplate\Service\MailTemplateContentBuilder;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    // Sending
    $services->set(SendMailHandler::class)
        ->args([
            service('mailer.transports'),
            service('shopware.filesystem.private'),
            service('logger'),
        ])
        ->tag('messenger.message_handler');

    $services->set(MailSender::class)
        ->public()
        ->args([
            service('mailer.mailer'),
            service('shopware.filesystem.private'),
            service(SystemConfigService::class),
            param('shopware.mail.max_body_length'),
            service('logger'),
            param('shopware.messenger.message_max_kib_size'),
            abstract_arg('message bus'),
            param('shopware.staging.mailing.disable_delivery'),
        ]);

    $services->set(MailFactory::class)
        ->public()
        ->args([
            service('validator'),
        ]);

    $services->set(MailService::class)
        ->args([
            service(DataValidator::class),
            service(StringTemplateRenderer::class),
            service(MailFactory::class),
            service(MailSender::class),
            service('media.repository'),
            service('sales_channel.repository'),
            service(SystemConfigService::class),
            service('event_dispatcher'),
            service('logger'),
            service(LanguageLocaleCodeProvider::class),
            service(MailTemplateContentBuilder::class),
            service(MailMetricsInstrumentor::class),
        ]);

    $services->set(MailGroupResolver::class);

    $services->set(MailMetricsInstrumentor::class)
        ->args([
            service(Meter::class),
            service(MailGroupResolver::class),
        ]);

    $services->set(SendMailTemplate::class)
        ->args([
            service(MailService::class),
            service('mail_template.repository'),
            service('logger'),
            service(Translator::class),
            service(LanguageLocaleCodeProvider::class),
            service(Connection::class),
        ]);

    $services->set(MailAttachmentsBuilder::class)
        ->public()
        ->args([
            service(MediaService::class),
            service('media.repository'),
            service(DocumentGenerator::class),
            service(Connection::class),
            service('document.repository'),
            service('monolog.logger.business_events'),
        ]);

    $services->set(MailPayloadFactory::class);

    $services->alias('core_mailer', 'mailer');

    $services->set(MailerTransportLoader::class)
        ->args([
            service('mailer.transport_factory'),
            service(SystemConfigService::class),
            service(MailAttachmentsBuilder::class),
            service('shopware.filesystem.public'),
            service('document.repository'),
        ]);

    $services->set(SmtpOauthTransportFactoryDecorator::class)
        ->decorate('mailer.transport_factory.smtp')
        ->args([
            service(SmtpOauthTransportFactoryDecorator::class . '.inner'),
            service(SmtpOauthAuthenticator::class),
        ]);

    $services->set(SmtpOauthAuthenticator::class)
        ->args([
            service(SmtpOauthTokenProvider::class),
        ]);

    $services->set(SmtpOauthTokenProvider::class)
        ->args([
            service('http_client'),
            service('cache.object'),
            service(SystemConfigService::class),
        ]);

    $services->set(FailedMessageSubscriber::class)
        ->args([
            service(Connection::class),
            service(ClockInterface::class),
        ])
        ->tag('kernel.event_subscriber');
};
