<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\Api\MailActionController;
use Shopware\Core\Content\MailTemplate\MailTemplateException;
use Shopware\Core\Content\MailTemplate\Service\MailTemplateService;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(MailActionController::class)]
class MailActionControllerTest extends TestCase
{
    private StringTemplateRenderer&MockObject $stringTemplateRenderer;

    protected function setUp(): void
    {
        $this->stringTemplateRenderer = $this->createMock(StringTemplateRenderer::class);
    }

    public function testBuild(): void
    {
        if (Feature::isActive('v6.8.0.0')) {
            static::markTestSkipped('New api.action.mail_template.build behavior');
        }

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

    public function testBuildWithoutTemplateData(): void
    {
        if (Feature::isActive('v6.8.0.0')) {
            static::markTestSkipped('New api.action.mail_template.build behavior');
        }

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

    public function testBuildWithoutTemplateContentThrows(): void
    {
        if (Feature::isActive('v6.8.0.0')) {
            static::markTestSkipped('New api.action.mail_template.build behavior');
        }

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
