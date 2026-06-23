<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Adapter\RenderingSpecificationResolver;
use Shopware\Core\Framework\ContentSystem\Api\ContentPreviewController;
use Shopware\Core\Framework\ContentSystem\Api\ContentPreviewRequest;
use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\ContentPipeline;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\DraftLayoutChecker;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ContentElementFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Output\Format\FullResponseFactory;
use Shopware\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\ContentSystem\RenderableLayout;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentRouteResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[CoversClass(ContentPreviewController::class)]
class ContentPreviewControllerTest extends TestCase
{
    #[TestDox('orchestrates decode, validation and pipeline, returning the full-format response')]
    public function testPreviewRendersDecodedLayoutThroughThePipeline(): void
    {
        $decodedElement = ContentElementBuilder::create('Sw:Content:Heading', 'e1')->build();
        $specification = $this->specification();
        $salesChannelContext = Generator::generateSalesChannelContext();
        $contentPage = new ContentPage('preview-layout', [$decodedElement], 'preview', null);

        $contextService = static::createStub(SalesChannelContextServiceInterface::class);
        $contextService->method('get')->willReturn($salesChannelContext);

        $resolver = static::createStub(RenderingSpecificationResolver::class);
        $resolver->method('resolveWithoutLayout')->willReturn($specification);

        $serializer = static::createStub(ContentElementFieldSerializer::class);
        $serializer->method('decodeElement')->willReturn($decodedElement);

        $pipeline = static::createMock(ContentPipeline::class);
        $pipeline->expects($this->atLeastOnce())
            ->method('load')
            ->with(
                static::callback(static fn (RenderableLayout $layout): bool => $layout->elements === [$decodedElement]
                    && $layout->reference->name === 'preview'
                    && $layout->reference->version === null),
                static::identicalTo($specification),
                static::isInstanceOf(RenderingCacheContext::class),
                RenderingMode::FULL,
                static::identicalTo($salesChannelContext),
            )
            ->willReturn($contentPage);

        $controller = new ContentPreviewController(
            $contextService,
            $resolver,
            $serializer,
            $this->checker(registered: true),
            $pipeline,
            new FullResponseFactory(),
        );

        $response = $controller->preview($this->request(), Context::createDefaultContext());

        static::assertInstanceOf(ContentRouteResponse::class, $response);
        static::assertSame($contentPage, $response->getContentPage());
    }

    #[TestDox('throws invalidLayoutStructure for an element missing a non-empty string id')]
    public function testPreviewThrowsForStructurallyInvalidElement(): void
    {
        $controller = new ContentPreviewController(
            $this->contextService(Generator::generateSalesChannelContext()),
            $this->resolverReturning($this->specification()),
            static::createStub(ContentElementFieldSerializer::class),
            $this->checker(registered: true),
            static::createStub(ContentPipeline::class),
            new FullResponseFactory(),
        );

        $request = $this->request(layout: [['component' => 'Sw:Content:Heading']]);

        $this->expectExceptionObject(ContentSystemException::invalidLayoutStructure(
            new ConstraintViolationList([
                new ConstraintViolation('Layout element id must be a non-empty string.', null, [], null, '[0].id', null),
            ])
        ));

        $controller->preview($request, Context::createDefaultContext());
    }

    #[TestDox('propagates unknownEntityType when the resolver cannot match the entity type')]
    public function testPreviewPropagatesUnknownEntityType(): void
    {
        $resolver = static::createStub(RenderingSpecificationResolver::class);
        $resolver->method('resolveWithoutLayout')
            ->willThrowException(ContentSystemException::unknownEntityType('mystery'));

        $controller = new ContentPreviewController(
            $this->contextService(Generator::generateSalesChannelContext()),
            $resolver,
            static::createStub(ContentElementFieldSerializer::class),
            $this->checker(registered: true),
            static::createStub(ContentPipeline::class),
            new FullResponseFactory(),
        );

        $this->expectExceptionObject(ContentSystemException::unknownEntityType('mystery'));

        $controller->preview($this->request(), Context::createDefaultContext());
    }

    #[TestDox('throws elementTypesInvalid when a component is not a registered element type')]
    public function testPreviewThrowsForUnregisteredComponent(): void
    {
        $decodedElement = ContentElementBuilder::create('Sw:Unknown:Widget', 'e1')->build();

        $serializer = static::createStub(ContentElementFieldSerializer::class);
        $serializer->method('decodeElement')->willReturn($decodedElement);

        $controller = new ContentPreviewController(
            $this->contextService(Generator::generateSalesChannelContext()),
            $this->resolverReturning($this->specification()),
            $serializer,
            $this->checker(registered: false),
            static::createStub(ContentPipeline::class),
            new FullResponseFactory(),
        );

        $this->expectExceptionObject(ContentSystemException::elementTypesInvalid(
            new ConstraintViolationList([
                new ConstraintViolation('Component "Sw:Unknown:Widget" is not a registered element type.', null, [], null, 'e1', null),
            ])
        ));

        $controller->preview($this->request(), Context::createDefaultContext());
    }

    #[TestDox('propagates sales channel context synthesis failures')]
    public function testPreviewPropagatesContextSynthesisFailure(): void
    {
        $failure = new \RuntimeException('invalid sales channel');

        $contextService = static::createStub(SalesChannelContextServiceInterface::class);
        $contextService->method('get')->willThrowException($failure);

        $controller = new ContentPreviewController(
            $contextService,
            static::createStub(RenderingSpecificationResolver::class),
            static::createStub(ContentElementFieldSerializer::class),
            $this->checker(registered: true),
            static::createStub(ContentPipeline::class),
            new FullResponseFactory(),
        );

        $this->expectExceptionObject($failure);

        $controller->preview($this->request(), Context::createDefaultContext());
    }

    /**
     * @param list<array<string, mixed>> $layout
     */
    private function request(array $layout = [['id' => 'e1', 'component' => 'Sw:Content:Heading']]): ContentPreviewRequest
    {
        return new ContentPreviewRequest(
            layout: $layout,
            entityType: 'product',
            entityId: 'prod-1',
            salesChannelId: 'sc-1',
        );
    }

    private function specification(): RenderingSpecification
    {
        return new RenderingSpecification([], PlaceholderValues::from([]), new Request());
    }

    private function contextService(SalesChannelContext $context): SalesChannelContextServiceInterface
    {
        $service = static::createStub(SalesChannelContextServiceInterface::class);
        $service->method('get')->willReturn($context);

        return $service;
    }

    private function resolverReturning(RenderingSpecification $specification): RenderingSpecificationResolver
    {
        $resolver = static::createStub(RenderingSpecificationResolver::class);
        $resolver->method('resolveWithoutLayout')->willReturn($specification);

        return $resolver;
    }

    private function checker(bool $registered): DraftLayoutChecker
    {
        $violations = new ConstraintViolationList();

        if (!$registered) {
            $violations->add(new ConstraintViolation('Component "Sw:Unknown:Widget" is not a registered element type.', null, [], null, 'e1', null));
        }

        $checker = static::createStub(DraftLayoutChecker::class);
        $checker->method('check')->willReturn($violations);

        return $checker;
    }
}
