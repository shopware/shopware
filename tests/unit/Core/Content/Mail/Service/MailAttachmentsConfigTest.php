<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Service\MailAttachmentsConfig;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(MailAttachmentsConfig::class)]
class MailAttachmentsConfigTest extends TestCase
{
    public function testMailAttachmentsConfigInstance(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = new MailTemplateEntity();
        $extension = new MailSendSubscriberConfig(false);
        $evenConfig = [];
        $orderId = Uuid::randomHex();

        $attachmentsConfig = new MailAttachmentsConfig(
            $context,
            $mailTemplate,
            $extension,
            $evenConfig,
            $orderId
        );

        static::assertEquals($context, $attachmentsConfig->getContext());
        static::assertEquals($mailTemplate, $attachmentsConfig->getMailTemplate());
        static::assertEquals($extension, $attachmentsConfig->getExtension());
        static::assertEquals($evenConfig, $attachmentsConfig->getEventConfig());
        static::assertEquals($orderId, $attachmentsConfig->getOrderId());

        $attachmentsConfig = $this->getMockBuilder(MailAttachmentsConfig::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $attachmentsConfig->setContext($context);
        $attachmentsConfig->setMailTemplate($mailTemplate);
        $attachmentsConfig->setExtension($extension);
        $attachmentsConfig->setEventConfig($evenConfig);
        $attachmentsConfig->setOrderId($orderId);

        static::assertEquals($context, $attachmentsConfig->getContext());
        static::assertEquals($mailTemplate, $attachmentsConfig->getMailTemplate());
        static::assertEquals($extension, $attachmentsConfig->getExtension());
        static::assertEquals($evenConfig, $attachmentsConfig->getEventConfig());
        static::assertEquals($orderId, $attachmentsConfig->getOrderId());
    }
}
