<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate\Request;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateException;
use Shopware\Core\Content\MailTemplate\Request\PreviewRequest;
use Shopware\Core\Content\MailTemplate\Request\Resolver\PreviewRequestResolver;
use Shopware\Core\Content\MailTemplate\Service\MailTemplateService;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\SalesChannelProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(PreviewRequestResolver::class)]
class PreviewRequestResolverTest extends TestCase
{
    private MailTemplateService&MockObject $mailTemplateService;

    private SalesChannelProvider&MockObject $salesChannelProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailTemplateService = $this->createMock(MailTemplateService::class);
        $this->salesChannelProvider = $this->createMock(SalesChannelProvider::class);
    }

    public function testResolveBuildsRequestAndFiltersUnknownEntities(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $salesChannel = new SalesChannelEntity();

        $request = $this->createRequest($context, [
            'mailTemplateId' => 'template-id',
            'entities' => [
                'order' => 'order-id',
                'customer' => 'customer-id',
            ],
            'templateData' => [
                'foo' => 'bar',
            ],
            'salesChannelId' => 'sales-channel-id',
            'includeHeaderFooter' => true,
            'strictRendering' => true,
        ]);

        $this->mailTemplateService->expects($this->once())
            ->method('loadTemplate')
            ->with('template-id', $context)
            ->willReturn($mailTemplate);

        $this->salesChannelProvider->expects($this->once())
            ->method('getData')
            ->with('sales-channel-id', $context)
            ->willReturn($salesChannel);

        $result = $this->resolveRequest($request);

        static::assertSame($mailTemplate, $result->mailTemplate);
        static::assertSame($salesChannel, $result->salesChannel);
        static::assertSame(['order' => 'order-id'], $result->entityMapping);
        static::assertSame(['foo' => 'bar'], $result->templateData);
        static::assertTrue($result->includeHeaderFooter);
        static::assertTrue($result->strictRendering);
    }

    public function testResolveKeepsEntitiesWhenMailTemplateTypeIsMissing(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = new MailTemplateEntity();

        $request = $this->createRequest($context, [
            'mailTemplateId' => 'template-id',
            'entities' => [
                'order' => 'order-id',
                'customer' => 'customer-id',
            ],
        ]);

        $this->mailTemplateService->method('loadTemplate')->willReturn($mailTemplate);

        $result = $this->resolveRequest($request);

        static::assertSame(
            [
                'order' => 'order-id',
                'customer' => 'customer-id',
            ],
            $result->entityMapping
        );
    }

    public function testResolveAcceptsPlainArrayValuesFromRequest(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $request = $this->createRequest($context, [
            'mailTemplateId' => 'template-id',
            'entities' => ['order' => 'order-id', 'customer' => 'customer-id'],
            'templateData' => ['foo' => 'bar'],
            'strictRendering' => true,
        ]);

        $this->mailTemplateService->expects($this->once())
            ->method('loadTemplate')
            ->with('template-id', $context)
            ->willReturn($mailTemplate);

        $result = $this->resolveRequest($request);

        static::assertSame(['order' => 'order-id'], $result->entityMapping);
        static::assertSame(['foo' => 'bar'], $result->templateData);
        static::assertTrue($result->strictRendering);
    }

    public function testResolveAcceptsStringBooleanValuesFromFormRequests(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $request = $this->createRequest($context, [
            'mailTemplateId' => 'template-id',
            'includeHeaderFooter' => '1',
            'strictRendering' => '0',
        ]);

        $this->mailTemplateService->expects($this->once())
            ->method('loadTemplate')
            ->with('template-id', $context)
            ->willReturn($mailTemplate);

        $result = $this->resolveRequest($request);

        static::assertTrue($result->includeHeaderFooter);
        static::assertFalse($result->strictRendering);
    }

    public function testResolveThrowsForInvalidEntities(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $request = $this->createRequest($context, [
            'mailTemplateId' => 'template-id',
            'entities' => 'invalid',
        ]);

        $this->mailTemplateService->expects($this->once())
            ->method('loadTemplate')
            ->with('template-id', $context)
            ->willReturn($mailTemplate);

        $this->expectExceptionObject(
            MailTemplateException::invalidRequestParameterType('entities', 'array|object', 'string')
        );

        $this->resolveRequest($request);
    }

    public function testResolveThrowsForInvalidTemplateData(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $request = $this->createRequest($context, [
            'mailTemplateId' => 'template-id',
            'templateData' => 'invalid',
        ]);

        $this->mailTemplateService->expects($this->once())
            ->method('loadTemplate')
            ->with('template-id', $context)
            ->willReturn($mailTemplate);

        $this->expectExceptionObject(
            MailTemplateException::invalidRequestParameterType('templateData', 'array|object', 'string')
        );

        $this->resolveRequest($request);
    }

    public function testResolveThrowsForInvalidSalesChannelIdType(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $request = $this->createRequest($context, [
            'mailTemplateId' => 'template-id',
            'salesChannelId' => 1,
        ]);

        $this->mailTemplateService->expects($this->once())
            ->method('loadTemplate')
            ->with('template-id', $context)
            ->willReturn($mailTemplate);

        $this->expectExceptionObject(
            MailTemplateException::invalidRequestParameterType('salesChannelId', 'string', 'int')
        );

        $this->resolveRequest($request);
    }

    public function testResolveThrowsForUnknownSalesChannelId(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $request = $this->createRequest($context, [
            'mailTemplateId' => 'template-id',
            'salesChannelId' => 'sales-channel-id',
        ]);

        $this->mailTemplateService->expects($this->once())
            ->method('loadTemplate')
            ->with('template-id', $context)
            ->willReturn($mailTemplate);

        $this->salesChannelProvider->expects($this->once())
            ->method('getData')
            ->with('sales-channel-id', $context)
            ->willReturn(null);

        $this->expectExceptionObject(
            MailTemplateException::invalidSalesChannelId('sales-channel-id')
        );

        $this->resolveRequest($request);
    }

    public function testResolveThrowsForInvalidIncludeHeaderFooter(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $request = $this->createRequest($context, [
            'mailTemplateId' => 'template-id',
            'includeHeaderFooter' => 'invalid',
        ]);

        $this->mailTemplateService->expects($this->once())
            ->method('loadTemplate')
            ->with('template-id', $context)
            ->willReturn($mailTemplate);

        $this->expectExceptionObject(
            MailTemplateException::invalidRequestParameterType('includeHeaderFooter', 'bool', 'string')
        );

        $this->resolveRequest($request);
    }

    public function testResolveThrowsForInvalidStrict(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $request = $this->createRequest($context, [
            'mailTemplateId' => 'template-id',
            'strictRendering' => 'invalid',
        ]);

        $this->mailTemplateService->expects($this->once())
            ->method('loadTemplate')
            ->with('template-id', $context)
            ->willReturn($mailTemplate);

        $this->expectExceptionObject(
            MailTemplateException::invalidRequestParameterType('strictRendering', 'bool', 'string')
        );

        $this->resolveRequest($request);
    }

    private function resolveRequest(Request $request): PreviewRequest
    {
        $resolver = new PreviewRequestResolver($this->mailTemplateService, $this->salesChannelProvider);

        return iterator_to_array(
            $resolver->resolve($request, new ArgumentMetadata('previewRequest', PreviewRequest::class, false, false, null))
        )[0];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createRequest(Context $context, array $payload): Request
    {
        $request = new Request([], $payload);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);

        return $request;
    }

    private function createMailTemplate(): MailTemplateEntity
    {
        $mailTemplateType = new MailTemplateTypeEntity();
        $mailTemplateType->setAvailableEntities([
            'order' => 'order',
        ]);

        $mailTemplate = new MailTemplateEntity();
        $mailTemplate->setMailTemplateType($mailTemplateType);

        return $mailTemplate;
    }
}
