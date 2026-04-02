<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\Api\MailActionController;
use Shopware\Core\Content\MailTemplate\MailTemplateException;
use Shopware\Core\Content\MailTemplate\Service\MailTemplateService;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(MailActionController::class)]
class MailActionControllerTest extends TestCase
{
    private StringTemplateRenderer&MockObject $stringTemplateRenderer;

    private MailTemplateService&MockObject $mailTemplateService;

    protected function setUp(): void
    {
        $this->stringTemplateRenderer = $this->createMock(StringTemplateRenderer::class);
        $this->mailTemplateService = $this->createMock(MailTemplateService::class);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testSendSuccess(): void
    {
        $orderId = Uuid::randomHex();

        $data = new RequestDataBag([
            'id' => 'random',
            'mailTemplateData' => [
                'order' => [
                    'id' => $orderId,
                ],
            ],
            'documentIds' => ['1'],
        ]);

        $context = Context::createDefaultContext();

        $this->mailTemplateService->expects($this->once())
            ->method('send')
            ->with(
                static::callback(static function (array $actual) use ($data): bool {
                    static::assertSame($data->all(), $actual);

                    return true;
                }),
                static::callback(static function (Context $actual) use ($context): bool {
                    static::assertSame($context, $actual);

                    return true;
                }),
                static::callback(static function (array $templateData) use ($orderId): bool {
                    static::assertSame(['order' => ['id' => $orderId]], $templateData);

                    return true;
                })
            );

        $mailActionController = new MailActionController(
            $this->stringTemplateRenderer,
            $this->mailTemplateService
        );

        $mailActionController->send($data, $context);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testSendDoesNotPassMediaIdsToExtensionToAvoidDuplication(): void
    {
        $mediaId = Uuid::randomHex();
        $data = new RequestDataBag([
            'id' => 'random',
            'mailTemplateData' => [],
            'mediaIds' => [$mediaId],
        ]);

        $this->mailService->expects($this->once())
            ->method('send')
            ->with(
                static::callback(static function (array $data) use ($mediaId) {
                    static::assertInstanceOf(MailAttachmentsConfig::class, $data['attachmentsConfig']);

                    /** @var MailAttachmentsConfig $config */
                    $config = $data['attachmentsConfig'];
                    $extension = $config->getExtension();

                    static::assertInstanceOf(MailSendSubscriberConfig::class, $extension);
                    static::assertSame([], $extension->getMediaIds());
                    static::assertSame([$mediaId], $data['mediaIds']);

                    return true;
                }),
                static::anything(),
                static::anything()
            );

        $mailActionController = new MailActionController(
            $this->mailService,
            $this->stringTemplateRenderer
        );

        $mailActionController->send($data, Context::createDefaultContext());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testBuild(): void
    {
        $templateData = [
            'order' => [
                'id' => Uuid::randomHex(),
            ],
        ];

        $data = new RequestDataBag([
            'mailTemplateType' => [
                'templateData' => $templateData,
            ],
            'mailTemplate' => [
                'contentHtml' => 'html',
            ],
        ]);

        $context = Context::createDefaultContext();

        $this->stringTemplateRenderer->expects($this->once())
            ->method('enableTestMode');
        $this->stringTemplateRenderer->expects($this->once())
            ->method('disableTestMode');
        $this->stringTemplateRenderer->expects($this->once())
            ->method('render')
            ->with('html', $templateData, $context)
            ->willReturn('rendered');

        $mailActionController = new MailActionController(
            $this->stringTemplateRenderer,
            $this->createMock(MailTemplateService::class),
        );

        $response = $mailActionController->build($data, $context);
        static::assertSame('"rendered"', $response->getContent());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testBuildWithoutTemplateData(): void
    {
        $data = new RequestDataBag([
            'mailTemplate' => [
                'contentHtml' => 'html',
            ],
        ]);

        $context = Context::createDefaultContext();

        $this->stringTemplateRenderer->expects($this->once())
            ->method('enableTestMode');
        $this->stringTemplateRenderer->expects($this->once())
            ->method('disableTestMode');
        $this->stringTemplateRenderer->expects($this->once())
            ->method('render')
            ->with('html', [], $context)
            ->willReturn('rendered');

        $mailActionController = new MailActionController(
            $this->stringTemplateRenderer,
            $this->createMock(MailTemplateService::class),
        );

        $response = $mailActionController->build($data, $context);
        static::assertSame('"rendered"', $response->getContent());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testBuildWithoutTemplateContentThrows(): void
    {
        $data = new RequestDataBag();

        $context = Context::createDefaultContext();

        $this->stringTemplateRenderer->expects($this->never())
            ->method('enableTestMode');
        $this->stringTemplateRenderer->expects($this->never())
            ->method('disableTestMode');
        $this->stringTemplateRenderer->expects($this->never())
            ->method('render');

        $mailActionController = new MailActionController(
            $this->stringTemplateRenderer,
            $this->createMock(MailTemplateService::class),
        );

        $this->expectExceptionObject(MailTemplateException::invalidMailTemplateContent());
        $mailActionController->build($data, $context);
    }
}
