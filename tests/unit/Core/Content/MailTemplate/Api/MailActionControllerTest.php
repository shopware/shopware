<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Payload\MailPayload;
use Shopware\Core\Content\Mail\Payload\MailPayloadFactory;
use Shopware\Core\Content\MailTemplate\Api\MailActionController;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateException;
use Shopware\Core\Content\MailTemplate\Request\GetDataAndSendRequest;
use Shopware\Core\Content\MailTemplate\Request\PreviewRequest;
use Shopware\Core\Content\MailTemplate\Request\SimulateRequest;
use Shopware\Core\Content\MailTemplate\Service\MailTemplateSendService;
use Shopware\Core\Content\MailTemplate\Service\MailTemplateService;
use Shopware\Core\Content\MailTemplate\Validation\MailTemplateRenderResult;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\Mime\Email;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(MailActionController::class)]
class MailActionControllerTest extends TestCase
{
    private StringTemplateRenderer&MockObject $stringTemplateRenderer;

    private MailTemplateService&MockObject $mailTemplateService;

    private MailTemplateSendService&MockObject $mailTemplateSendService;

    private MailPayloadFactory&MockObject $mailPayloadFactory;

    protected function setUp(): void
    {
        $this->stringTemplateRenderer = $this->createMock(StringTemplateRenderer::class);
        $this->mailTemplateService = $this->createMock(MailTemplateService::class);
        $this->mailTemplateSendService = $this->createMock(MailTemplateSendService::class);
        $this->mailPayloadFactory = $this->createMock(MailPayloadFactory::class);
    }

    public function testSendSuccess(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = new MailTemplateEntity();
        $mailPayload = new MailPayload(subject: 'subject');
        $data = new RequestDataBag([
            'mailTemplateId' => 'template-id',
            'mailTemplateData' => [
                'order' => [
                    'id' => 'order-id',
                ],
            ],
        ]);

        $this->mailPayloadFactory->expects($this->once())
            ->method('make')
            ->with($data)
            ->willReturn($mailPayload);

        $this->mailTemplateService->expects($this->once())
            ->method('loadTemplate')
            ->with('template-id', $context)
            ->willReturn($mailTemplate);

        $this->mailTemplateSendService->expects($this->once())
            ->method('send')
            ->with($mailPayload, $context, ['order' => ['id' => 'order-id']], $mailTemplate)
            ->willReturn($this->createEmail());

        $response = $this->createController()->send($data, $context);

        static::assertGreaterThan(0, $this->decodeResponse($response)['size']);
    }

    public function testSendWithoutTemplateIdNormalizesInvalidTemplateData(): void
    {
        $context = Context::createDefaultContext();
        $mailPayload = new MailPayload();
        $data = new RequestDataBag([
            'mailTemplateData' => 'invalid',
        ]);

        $this->mailPayloadFactory->expects($this->once())
            ->method('make')
            ->with($data)
            ->willReturn($mailPayload);

        $this->mailTemplateService->expects($this->never())
            ->method('loadTemplate');

        $this->mailTemplateSendService->expects($this->once())
            ->method('send')
            ->with($mailPayload, $context, [], null)
            ->willReturn(null);

        $response = $this->createController()->send($data, $context);

        static::assertSame('{"size":0}', $response->getContent());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testBuild(): void
    {
        $templateData = [
            'order' => [
                'id' => 'order-id',
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

        $response = $this->createController()->build($data, $context);

        static::assertSame('"rendered"', $response->getContent());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testBuildWithoutTemplateContentThrows(): void
    {
        $this->stringTemplateRenderer->expects($this->never())
            ->method('enableTestMode');
        $this->stringTemplateRenderer->expects($this->never())
            ->method('disableTestMode');
        $this->stringTemplateRenderer->expects($this->never())
            ->method('render');

        $this->expectExceptionObject(MailTemplateException::invalidMailTemplateContent());

        $this->createController()->build(new RequestDataBag(), Context::createDefaultContext());
    }

    public function testSimulate(): void
    {
        $context = Context::createDefaultContext();
        $simulateRequest = new SimulateRequest(
            templateParts: ['contentHtml' => 'Hello {{ email }}'],
            eventName: 'checkout.customer.before.login',
            strictRendering: true,
        );

        $result = [
            'contentHtml' => MailTemplateRenderResult::success('Hello test@example.com'),
        ];

        $this->mailTemplateService->expects($this->once())
            ->method('simulate')
            ->with($simulateRequest, $context)
            ->willReturn($result);

        $response = $this->createController()->simulate($simulateRequest, $context);

        static::assertSame(
            [
                'contentHtml' => [
                    'type' => 'success',
                    'content' => 'Hello test@example.com',
                ],
            ],
            $this->decodeResponse($response)
        );
    }

    public function testPreview(): void
    {
        $context = Context::createDefaultContext();
        $previewRequest = new PreviewRequest(new MailTemplateEntity(), strictRendering: false);

        $result = [
            'subject' => MailTemplateRenderResult::success('Subject'),
        ];

        $this->mailTemplateService->expects($this->once())
            ->method('preview')
            ->with($previewRequest, $context)
            ->willReturn($result);

        $response = $this->createController()->preview($previewRequest, $context);

        static::assertSame(
            [
                'subject' => [
                    'type' => 'success',
                    'content' => 'Subject',
                ],
            ],
            $this->decodeResponse($response)
        );
    }

    public function testGetDataAndSend(): void
    {
        $context = Context::createDefaultContext();
        $request = new GetDataAndSendRequest(new MailTemplateEntity());

        $this->mailTemplateSendService->expects($this->once())
            ->method('getTemplateDataAndSend')
            ->with($request, $context)
            ->willReturn($this->createEmail());

        $response = $this->createController()->getDataAndSend($request, $context);

        static::assertGreaterThan(0, $this->decodeResponse($response)['size']);
    }

    public function testAvailableVariables(): void
    {
        $context = Context::createDefaultContext();
        $request = new RequestDataBag([
            'eventName' => 'checkout.customer.before.login',
            'parentVariablePath' => 'customer',
        ]);

        $this->mailTemplateService->expects($this->once())
            ->method('getAvailableVariables')
            ->with('checkout.customer.before.login', $context, 'customer')
            ->willReturn([['fieldName' => 'email', 'hasChildren' => false]]);

        $response = $this->createController()->availableVariables($request, $context);

        static::assertSame('[{"fieldName":"email","hasChildren":false}]', $response->getContent());
    }

    private function createController(): MailActionController
    {
        return new MailActionController(
            $this->stringTemplateRenderer,
            $this->mailTemplateService,
            $this->mailTemplateSendService,
            $this->mailPayloadFactory,
        );
    }

    private function createEmail(): Email
    {
        return (new Email())
            ->from('sender@example.com')
            ->to('recipient@example.com')
            ->text('sent');
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(object $response): array
    {
        \assert(method_exists($response, 'getContent'));

        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }
}
