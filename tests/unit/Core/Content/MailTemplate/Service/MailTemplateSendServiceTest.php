<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Mail\Payload\MailPayload;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\Mail\Service\MailAttachmentsConfig;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\Request\GetDataAndSendRequest;
use Shopware\Core\Content\MailTemplate\Service\MailDataProvider;
use Shopware\Core\Content\MailTemplate\Service\MailTemplateSendService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Symfony\Component\Mime\Email;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(MailTemplateSendService::class)]
class MailTemplateSendServiceTest extends TestCase
{
    private AbstractMailService&MockObject $mailService;

    private MailDataProvider&MockObject $mailDataProvider;

    private AbstractTranslator&Stub $translator;

    private LanguageLocaleCodeProvider&Stub $languageLocaleProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailService = $this->createMock(AbstractMailService::class);
        $this->mailDataProvider = $this->createMock(MailDataProvider::class);
        $this->translator = static::createStub(AbstractTranslator::class);
        $this->languageLocaleProvider = static::createStub(LanguageLocaleCodeProvider::class);
    }

    public function testGetTemplateDataAndSendUsesProviderDataAndTemplateForAttachments(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = new MailTemplateEntity();
        $mailPayload = new MailPayload(
            recipients: ['test@example.com' => 'Test'],
            subject: 'Subject',
            senderName: 'Shopware',
            documentIds: ['document-id'],
            mediaIds: ['media-id']
        );
        $request = new GetDataAndSendRequest($mailTemplate, ['order' => 'order-id'], ['foo' => 'bar'], $mailPayload);

        $this->mailDataProvider->expects($this->once())
            ->method('getTemplateData')
            ->with($mailTemplate, ['order' => 'order-id'], $context, ['foo' => 'bar'])
            ->willReturn(['order' => ['id' => 'order-id']]);

        $this->mailService->expects($this->once())
            ->method('send')
            ->with(
                static::callback(function (array $data) use ($mailTemplate): bool {
                    static::assertArrayHasKey('attachmentsConfig', $data);
                    static::assertInstanceOf(MailAttachmentsConfig::class, $data['attachmentsConfig']);
                    static::assertSame($mailTemplate, $data['attachmentsConfig']->getMailTemplate());
                    static::assertSame('order-id', $data['attachmentsConfig']->getOrderId());
                    static::assertSame(['document-id'], $data['attachmentsConfig']->getExtension()->getDocumentIds());
                    static::assertSame(['media-id'], $data['attachmentsConfig']->getExtension()->getMediaIds());

                    return true;
                }),
                $context,
                ['order' => ['id' => 'order-id']]
            )
            ->willReturn(null);

        $mailTemplateSendService = $this->createService();

        static::assertNull($mailTemplateSendService->getTemplateDataAndSend($request, $context));
    }

    public function testSendBuildsAttachmentsConfigFromOrderEntityWithoutMailTemplate(): void
    {
        $context = Context::createDefaultContext();
        $order = new OrderEntity();
        $order->setId('order-id');

        $this->mailDataProvider->expects($this->never())
            ->method('getTemplateData');

        $this->mailService->expects($this->once())
            ->method('send')
            ->with(
                static::callback(function (array $data): bool {
                    static::assertArrayHasKey('attachmentsConfig', $data);
                    static::assertInstanceOf(MailAttachmentsConfig::class, $data['attachmentsConfig']);
                    static::assertSame('order-id', $data['attachmentsConfig']->getOrderId());

                    return true;
                }),
                $context,
                ['order' => $order]
            )
            ->willReturn(static::createStub(Email::class));

        $mailTemplateSendService = $this->createService();

        $result = $mailTemplateSendService->send(
            new MailPayload(subject: 'Subject', senderName: 'Sender'),
            $context,
            ['order' => $order]
        );

        static::assertInstanceOf(Email::class, $result);
    }

    public function testSendInjectsSalesChannelIntoTranslatorWhileSending(): void
    {
        $context = Context::createDefaultContext();
        $calls = [];

        $this->languageLocaleProvider->method('getLocaleForLanguageId')->willReturn('de-DE');

        $translator = $this->createMock(AbstractTranslator::class);
        $translator->expects($this->once())
            ->method('injectSettings')
            ->with('sales-channel-id', Defaults::LANGUAGE_SYSTEM, 'de-DE', $context)
            ->willReturnCallback(static function () use (&$calls): void {
                $calls[] = 'inject';
            });

        $translator->expects($this->once())
            ->method('resetInjection')
            ->willReturnCallback(static function () use (&$calls): void {
                $calls[] = 'reset';
            });

        $this->mailDataProvider->expects($this->never())->method('getTemplateData');

        $this->mailService->expects($this->once())
            ->method('send')
            ->willReturnCallback(static function () use (&$calls): ?Email {
                $calls[] = 'send';

                return null;
            });

        $this->createService($translator)->send(new MailPayload(salesChannelId: 'sales-channel-id'), $context, []);

        static::assertSame(['inject', 'send', 'reset'], $calls);
    }

    public function testSendResetsTranslatorWhenSendingFails(): void
    {
        $exception = new \RuntimeException('Mailer is down');

        $translator = $this->createMock(AbstractTranslator::class);
        $translator->expects($this->once())->method('injectSettings');
        $translator->expects($this->once())->method('resetInjection');

        $this->mailDataProvider->expects($this->never())->method('getTemplateData');
        $this->mailService->expects($this->once())->method('send')->willThrowException($exception);

        $this->expectExceptionObject($exception);

        $this->createService($translator)->send(new MailPayload(salesChannelId: 'sales-channel-id'), Context::createDefaultContext(), []);
    }

    public function testSendDoesNotInjectTranslatorWithoutSalesChannel(): void
    {
        $translator = $this->createMock(AbstractTranslator::class);
        $translator->expects($this->never())->method('injectSettings');
        $translator->expects($this->never())->method('resetInjection');

        $this->mailDataProvider->expects($this->never())->method('getTemplateData');
        $this->mailService->expects($this->once())->method('send');

        $this->createService($translator)->send(new MailPayload(), Context::createDefaultContext(), []);
    }

    public function testSendKeepsTranslatorSettingsThatAreAlreadyInjected(): void
    {
        $translator = $this->createMock(AbstractTranslator::class);
        $translator->method('getSnippetSetId')->willReturn('snippet-set-id');
        $translator->expects($this->never())->method('injectSettings');
        $translator->expects($this->never())->method('resetInjection');

        $this->mailDataProvider->expects($this->never())->method('getTemplateData');
        $this->mailService->expects($this->once())->method('send');

        $this->createService($translator)->send(new MailPayload(salesChannelId: 'sales-channel-id'), Context::createDefaultContext(), []);
    }

    private function createService(?AbstractTranslator $translator = null): MailTemplateSendService
    {
        return new MailTemplateSendService(
            $this->mailService,
            $this->mailDataProvider,
            $translator ?? $this->translator,
            $this->languageLocaleProvider,
        );
    }
}
