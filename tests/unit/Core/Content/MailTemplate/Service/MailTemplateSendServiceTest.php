<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Mail\Payload\MailPayload;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\Mail\Service\MailAttachmentsConfig;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\Request\GetDataAndSendRequest;
use Shopware\Core\Content\MailTemplate\Service\MailDataProvider;
use Shopware\Core\Content\MailTemplate\Service\MailTemplateSendService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Mime\Email;

/**
 * @internal
 */
#[CoversClass(MailTemplateSendService::class)]
#[Package('after-sales')]
class MailTemplateSendServiceTest extends TestCase
{
    private AbstractMailService&MockObject $mailService;

    private MailDataProvider&MockObject $mailDataProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailService = $this->createMock(AbstractMailService::class);
        $this->mailDataProvider = $this->createMock(MailDataProvider::class);
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
            ->willReturn($this->createMock(Email::class));

        $mailTemplateSendService = $this->createService();

        $result = $mailTemplateSendService->send(
            new MailPayload(subject: 'Subject', senderName: 'Sender'),
            $context,
            ['order' => $order]
        );

        static::assertInstanceOf(Email::class, $result);
    }

    private function createService(): MailTemplateSendService
    {
        return new MailTemplateSendService(
            $this->mailService,
            $this->mailDataProvider,
        );
    }
}
