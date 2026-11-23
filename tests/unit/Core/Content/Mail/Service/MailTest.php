<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Service\Mail;
use Shopware\Core\Content\Mail\Service\MailAttachmentsConfig;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[CoversClass(Mail::class)]
#[Package('after-sales')]
class MailTest extends TestCase
{
    use EnvTestBehaviour;

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

        $restored = unserialize(serialize($mail));
        static::assertInstanceOf(Mail::class, $restored);
        static::assertEquals($attachmentsConfig, $restored->getMailAttachmentsConfig());
        static::assertSame(['foobar'], $restored->getAttachmentUrls());
    }

    #[DataProvider('sensitivityProvider')]
    public function testSensitivitySurvivesSerializationAndCloning(bool $sensitive): void
    {
        $this->setEnvVars(['V6_8_0_0' => '1']);

        $mail = (new Mail())->subject('Subject')->from('shop@example.com')->to('customer@example.com')->text('Body');
        $mail->setSensitive($sensitive);

        static::assertTrue(array_is_list($mail->__serialize()));
        static::assertSame([null, [], $sensitive], \array_slice($mail->__serialize(), -3));
        static::assertSame($sensitive, (clone $mail)->isSensitive());
        $restored = unserialize(serialize($mail));
        static::assertInstanceOf(Mail::class, $restored);
        static::assertSame($sensitive, $restored->isSensitive());
        static::assertStringNotContainsString('X-Shopware-Sensitive', $mail->toString());
    }

    #[DataProvider('sensitivityProvider')]
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testSensitivitySurvivesSerializationBeforeNextMajor(bool $sensitive): void
    {
        $mail = (new Mail())->text('Body')->addAttachmentUrl('attachment.pdf')->setSensitive($sensitive);
        $restored = unserialize(serialize($mail));

        static::assertInstanceOf(Mail::class, $restored);
        static::assertSame($sensitive, $restored->isSensitive());
        static::assertSame('Body', $restored->getTextBody());
        static::assertSame(['attachment.pdf'], $restored->getAttachmentUrls());
    }

    /**
     * @return \Generator<string, array{bool}>
     */
    public static function sensitivityProvider(): \Generator
    {
        yield 'ordinary Shopware mail' => [false];
        yield 'sensitive Shopware mail' => [true];
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testPreviouslyQueuedMailWithoutClassificationKeepsDefault(): void
    {
        $mail = (new Mail())->text('Body')->addAttachmentUrl('attachment.pdf');
        $data = $mail->__serialize();
        array_pop($data);

        $restored = new Mail();
        $restored->__unserialize($data);

        static::assertFalse($restored->isSensitive());
        static::assertSame('Body', $restored->getTextBody());
        static::assertSame(['attachment.pdf'], $restored->getAttachmentUrls());
    }
}
