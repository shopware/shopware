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
use Shopware\Core\Content\MailTemplate\Request\GetDataAndSendRequestFactory;
use Shopware\Core\Content\MailTemplate\Request\PreviewRequest;
use Shopware\Core\Content\MailTemplate\Request\PreviewRequestFactory;
use Shopware\Core\Content\MailTemplate\Service\MailTemplateService;
use Shopware\Core\Content\MailTemplate\Validation\MailTemplateRenderResultCollection;
use Shopware\Core\Content\MailTemplate\Validation\MailTemplateRenderSuccess;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
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

    private MailPayloadFactory&MockObject $mailPayloadFactory;

    private PreviewRequestFactory&MockObject $previewRequestFactory;

    private GetDataAndSendRequestFactory&MockObject $getDataAndSendRequestFactory;

    protected function setUp(): void
    {
        $this->stringTemplateRenderer = $this->createMock(StringTemplateRenderer::class);
        $this->mailTemplateService = $this->createMock(MailTemplateService::class);
        $this->mailPayloadFactory = $this->createMock(MailPayloadFactory::class);
        $this->previewRequestFactory = $this->createMock(PreviewRequestFactory::class);
        $this->getDataAndSendRequestFactory = $this->createMock(GetDataAndSendRequestFactory::class);
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

        $this->mailTemplateService->expects($this->once())
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

        $this->mailTemplateService->expects($this->once())
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
        $request = new RequestDataBag([
            'mailTemplateContent' => new DataBag([
                'contentHtml' => 'Hello {{ email }}',
            ]),
            'eventName' => 'checkout.customer.before.login',
            'strict' => true,
        ]);

        $result = new MailTemplateRenderResultCollection();
        $result->set('contentHtml', new MailTemplateRenderSuccess('Hello test@example.com'));

        $this->mailTemplateService->expects($this->once())
            ->method('simulate')
            ->with(
                ['contentHtml' => 'Hello {{ email }}'],
                'checkout.customer.before.login',
                $context,
                true
            )
            ->willReturn($result);

        $response = $this->createController()->simulate($request, $context);

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

    public function testSimulateAcceptsArrayMailTemplateContent(): void
    {
        $context = Context::createDefaultContext();
        $request = $this->createMock(RequestDataBag::class);

        $request->method('get')
            ->willReturnMap([
                ['mailTemplateContent', null, ['contentHtml' => 'Hello {{ email }}']],
                ['eventName', null, 'checkout.customer.before.login'],
                ['strict', false, true],
            ]);

        $result = new MailTemplateRenderResultCollection();
        $result->set('contentHtml', new MailTemplateRenderSuccess('Hello test@example.com'));

        $this->mailTemplateService->expects($this->once())
            ->method('simulate')
            ->with(
                ['contentHtml' => 'Hello {{ email }}'],
                'checkout.customer.before.login',
                $context,
                true
            )
            ->willReturn($result);

        $response = $this->createController()->simulate($request, $context);

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

    public function testSimulateThrowsForInvalidMailTemplateContent(): void
    {
        $request = new RequestDataBag([
            'mailTemplateContent' => 'invalid',
            'eventName' => 'checkout.customer.before.login',
        ]);

        $this->expectExceptionObject(
            MailTemplateException::invalidRequestParameterType('mailTemplateContent', 'array|object', 'string')
        );

        $this->createController()->simulate($request, Context::createDefaultContext());
    }

    public function testPreview(): void
    {
        $context = Context::createDefaultContext();
        $request = new RequestDataBag([
            'strict' => false,
        ]);
        $previewRequest = new PreviewRequest(new MailTemplateEntity());

        $result = new MailTemplateRenderResultCollection();
        $result->set('subject', new MailTemplateRenderSuccess('Subject'));

        $this->previewRequestFactory->expects($this->once())
            ->method('make')
            ->with($request, $context)
            ->willReturn($previewRequest);

        $this->mailTemplateService->expects($this->once())
            ->method('preview')
            ->with($previewRequest, $context, false)
            ->willReturn($result);

        $response = $this->createController()->preview($request, $context);

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
        $request = new RequestDataBag();
        $sendRequest = new GetDataAndSendRequest(new MailTemplateEntity());

        $this->getDataAndSendRequestFactory->expects($this->once())
            ->method('make')
            ->with($request, $context)
            ->willReturn($sendRequest);

        $this->mailTemplateService->expects($this->once())
            ->method('getTemplateDataAndSend')
            ->with($sendRequest, $context)
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
            $this->mailPayloadFactory,
            $this->previewRequestFactory,
            $this->getDataAndSendRequestFactory,
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
