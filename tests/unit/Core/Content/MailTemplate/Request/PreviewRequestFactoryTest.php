<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate\Request;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateException;
use Shopware\Core\Content\MailTemplate\Request\PreviewRequestFactory;
use Shopware\Core\Content\MailTemplate\Service\MailTemplateService;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\SalesChannelProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(PreviewRequestFactory::class)]
class PreviewRequestFactoryTest extends TestCase
{
    private MailTemplateService&MockObject $mailTemplateService;

    private SalesChannelProvider&MockObject $salesChannelProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailTemplateService = $this->createMock(MailTemplateService::class);
        $this->salesChannelProvider = $this->createMock(SalesChannelProvider::class);
    }

    public function testMakeBuildsRequestAndFiltersUnknownEntities(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $salesChannel = new SalesChannelEntity();

        $request = new RequestDataBag([
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

        $factory = new PreviewRequestFactory($this->mailTemplateService, $this->salesChannelProvider);

        $result = $factory->make($request, $context);

        static::assertSame($mailTemplate, $result->mailTemplate);
        static::assertSame($salesChannel, $result->salesChannel);
        static::assertSame(['order' => 'order-id'], $result->entityMapping);
        static::assertSame(['foo' => 'bar'], $result->templateData);
        static::assertTrue($result->includeHeaderFooter);
        static::assertTrue($result->strictRendering);
    }

    public function testMakeKeepsEntitiesWhenMailTemplateTypeIsMissing(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = new MailTemplateEntity();

        $request = new RequestDataBag([
            'mailTemplateId' => 'template-id',
            'entities' => [
                'order' => 'order-id',
                'customer' => 'customer-id',
            ],
        ]);

        $this->mailTemplateService->method('loadTemplate')->willReturn($mailTemplate);

        $factory = new PreviewRequestFactory($this->mailTemplateService, $this->salesChannelProvider);

        $result = $factory->make($request, $context);

        static::assertSame(
            [
                'order' => 'order-id',
                'customer' => 'customer-id',
            ],
            $result->entityMapping
        );
    }

    public function testMakeAcceptsPlainArrayValuesFromRequest(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $request = new RequestDataBag([
            'mailTemplateId' => 'template-id',
            'entities' => ['order' => 'order-id', 'customer' => 'customer-id'],
            'templateData' => ['foo' => 'bar'],
            'strictRendering' => true,
        ]);

        $this->mailTemplateService->expects($this->once())
            ->method('loadTemplate')
            ->with('template-id', $context)
            ->willReturn($mailTemplate);

        $factory = new PreviewRequestFactory($this->mailTemplateService, $this->salesChannelProvider);

        $result = $factory->make($request, $context);

        static::assertSame(['order' => 'order-id'], $result->entityMapping);
        static::assertSame(['foo' => 'bar'], $result->templateData);
        static::assertTrue($result->strictRendering);
    }

    public function testMakeAcceptsStringBooleanValuesFromFormRequests(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $request = new RequestDataBag([
            'mailTemplateId' => 'template-id',
            'includeHeaderFooter' => '1',
            'strictRendering' => '0',
        ]);

        $this->mailTemplateService->expects($this->once())
            ->method('loadTemplate')
            ->with('template-id', $context)
            ->willReturn($mailTemplate);

        $factory = new PreviewRequestFactory($this->mailTemplateService, $this->salesChannelProvider);

        $result = $factory->make($request, $context);

        static::assertTrue($result->includeHeaderFooter);
        static::assertFalse($result->strictRendering);
    }

    public function testMakeThrowsForInvalidEntities(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $request = new RequestDataBag([
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

        (new PreviewRequestFactory($this->mailTemplateService, $this->salesChannelProvider))->make($request, $context);
    }

    public function testMakeThrowsForInvalidTemplateData(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $request = new RequestDataBag([
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

        (new PreviewRequestFactory($this->mailTemplateService, $this->salesChannelProvider))->make($request, $context);
    }

    public function testMakeThrowsForInvalidSalesChannelIdType(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $request = new RequestDataBag([
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

        (new PreviewRequestFactory($this->mailTemplateService, $this->salesChannelProvider))->make($request, $context);
    }

    public function testMakeThrowsForUnknownSalesChannelId(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $request = new RequestDataBag([
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

        (new PreviewRequestFactory($this->mailTemplateService, $this->salesChannelProvider))->make($request, $context);
    }

    public function testMakeThrowsForInvalidIncludeHeaderFooter(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $request = new RequestDataBag([
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

        (new PreviewRequestFactory($this->mailTemplateService, $this->salesChannelProvider))->make($request, $context);
    }

    public function testMakeThrowsForInvalidStrict(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $request = new RequestDataBag([
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

        (new PreviewRequestFactory($this->mailTemplateService, $this->salesChannelProvider))->make($request, $context);
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
