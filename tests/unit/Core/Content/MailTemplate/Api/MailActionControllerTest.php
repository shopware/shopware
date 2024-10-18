<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\Mail\Service\MailAttachmentsConfig;
use Shopware\Core\Content\MailTemplate\Api\MailActionController;
use Shopware\Core\Content\MailTemplate\MailTemplateException;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(MailActionController::class)]
class MailActionControllerTest extends TestCase
{
    /**
     * @var array<string, string>
     */
    private static array $assertions;

    private AbstractMailService&MockObject $mailService;

    private StringTemplateRenderer&MockObject $stringTemplateRenderer;

    protected function setUp(): void
    {
        $this->stringTemplateRenderer = $this->createMock(StringTemplateRenderer::class);
        $this->mailService = $this->createMock(AbstractMailService::class);
        self::$assertions = [];
    }

    protected function tearDown(): void
    {
        static::assertEmpty(self::$assertions);
        parent::tearDown();
    }

    public function testSendSuccess(): void
    {
        $data = new RequestDataBag([
            'id' => 'random',
            'mailTemplateData' => [
                'order' => [
                    'id' => Uuid::randomHex(),
                ],
            ],
            'documentIds' => ['1'],
        ]);

        $this->mailService->expects(static::once())
            ->method('send')
            ->with(
                static::callback(function (array $data) {
                    static::assertArrayHasKey('attachmentsConfig', $data);
                    static::assertInstanceOf(MailAttachmentsConfig::class, $data['attachmentsConfig']);

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

    public function testBuild(): void
    {
        $orderId = Uuid::randomHex();
        $templateData = [
            'order' => [
                'id' => $orderId,
            ],
        ];

        $data = new RequestDataBag([
            'mailTemplateType' => [
                'templateData' => $templateData,
            ],
            'mailTemplate' => [
                'contentHtml' => 'html',
                'contentPlain' => 'text',
            ],
        ]);

        $context = Context::createDefaultContext();

        $this->stringTemplateRenderer->expects(static::once())
            ->method('enableTestMode');
        $this->stringTemplateRenderer->expects(static::once())
            ->method('disableTestMode');

        // make sure render is called twice with the correct parameter
        self::$assertions['html_' . $orderId] = 'rendered-html';
        self::$assertions['text_' . $orderId] = 'rendered-text';
        $this->stringTemplateRenderer->expects(static::exactly(2))
            ->method('render')
            ->willReturnCallback(static function ($template, $data): string {
                $return = self::$assertions[$template . '_' . $data['order']['id']];
                unset(self::$assertions[$template . '_' . $data['order']['id']]);

                return $return;
            });

        $mailActionController = new MailActionController(
            $this->mailService,
            $this->stringTemplateRenderer
        );

        $response = $mailActionController->build($data, $context);
        $expected = json_encode([
            'html' => 'rendered-html',
            'plain' => 'rendered-text',
        ], \JSON_THROW_ON_ERROR);
        static::assertSame($expected, $response->getContent());
    }

    public function testBuildWithoutTemplateData(): void
    {
        $data = new RequestDataBag([
            'mailTemplate' => [
                'contentHtml' => 'html',
                'contentPlain' => 'text',
            ],
        ]);

        $context = Context::createDefaultContext();

        $this->stringTemplateRenderer->expects(static::once())
            ->method('enableTestMode');
        $this->stringTemplateRenderer->expects(static::once())
            ->method('disableTestMode');

        // make sure render is called twice with the correct parameter
        self::$assertions['html'] = 'rendered-html';
        self::$assertions['text'] = 'rendered-text';

        $this->stringTemplateRenderer->expects(static::exactly(2))
            ->method('render')
            ->willReturnCallback(static function ($template, $data): string {
                $return = self::$assertions[$template];
                unset(self::$assertions[$template]);

                return $return;
            });

        $mailActionController = new MailActionController(
            $this->mailService,
            $this->stringTemplateRenderer
        );

        $response = $mailActionController->build($data, $context);

        $expected = json_encode([
            'html' => 'rendered-html',
            'plain' => 'rendered-text',
        ], \JSON_THROW_ON_ERROR);
        static::assertSame($expected, $response->getContent());
    }

    public function testBuildWithoutTemplateContentThrows(): void
    {
        $data = new RequestDataBag();

        $context = Context::createDefaultContext();

        $this->stringTemplateRenderer->expects(static::never())
            ->method('enableTestMode');
        $this->stringTemplateRenderer->expects(static::never())
            ->method('disableTestMode');
        $this->stringTemplateRenderer->expects(static::never())
            ->method('render');

        $mailActionController = new MailActionController(
            $this->mailService,
            $this->stringTemplateRenderer
        );

        $this->expectExceptionObject(MailTemplateException::invalidMailTemplateContent());
        $mailActionController->build($data, $context);
    }
}
