<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Service\Mail;
use Shopware\Core\Content\Mail\Service\MailAttachmentsConfig;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(Mail::class)]
class MailTest extends TestCase
{
    public function testMailInstance(): void
    {
        $mail = new Mail();
        $mail->addAttachmentUrl('foobar');

        static::assertEquals(['foobar'], $mail->getAttachmentUrls());

        $attachmentsConfig = new MailAttachmentsConfig(
            Context::createDefaultContext(),
            new MailTemplateEntity(),
            new MailSendSubscriberConfig(false),
            [],
            Uuid::randomHex()
        );

        $mail->setMailAttachmentsConfig($attachmentsConfig);

        static::assertEquals($attachmentsConfig, $mail->getMailAttachmentsConfig());
    }
}
