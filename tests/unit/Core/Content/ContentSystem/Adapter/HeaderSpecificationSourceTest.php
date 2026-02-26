<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\ContentLayoutAssignmentInterface;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\HeaderContentLayout\HeaderContentLayoutCollection;
use Shopware\Core\Content\ContentSystem\Adapter\FactoryHelper\DomainAwareLayoutResolver;
use Shopware\Core\Content\ContentSystem\Adapter\HeaderSpecificationSource;
use Shopware\Core\Content\ContentSystem\ContentSection;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Helper\RequestDataExtractor;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(HeaderSpecificationSource::class)]
class HeaderSpecificationSourceTest extends TestCase
{
    private DomainAwareLayoutResolver&Stub $resolver;

    private HeaderSpecificationSource $source;

    protected function setUp(): void
    {
        $this->resolver = static::createStub(DomainAwareLayoutResolver::class);

        /** @var StaticEntityRepository<HeaderContentLayoutCollection> $repository */
        $repository = new StaticEntityRepository([]);

        $this->source = new HeaderSpecificationSource(
            $this->resolver,
            $repository,
            new RequestDataExtractor()
        );
    }

    #[TestDox('always returns true for supports')]
    public function testSupportsAlwaysReturnsTrue(): void
    {
        $context = Generator::generateSalesChannelContext();

        static::assertTrue($this->source->supports('', new Request(), $context));
    }

    #[TestDox('resolves layout ID from domain-aware assignment')]
    public function testResolveLayoutIdReturnsLayoutIdFromAssignment(): void
    {
        $layoutId = Uuid::randomHex();
        $assignment = static::createStub(ContentLayoutAssignmentInterface::class);
        $assignment->method('getContentLayoutId')->willReturn($layoutId);

        $this->resolver->method('resolve')->willReturn($assignment);

        $context = Generator::generateSalesChannelContext();

        static::assertSame($layoutId, $this->source->resolveLayoutId('', new Request(), $context));
    }

    #[TestDox('returns empty data requirements in specification data')]
    public function testResolveSpecificationDataReturnsEmptyDataRequirements(): void
    {
        $assignment = static::createStub(ContentLayoutAssignmentInterface::class);
        $assignment->method('getParameterBindings')->willReturn(null);

        $this->resolver->method('resolve')->willReturn($assignment);

        $context = Generator::generateSalesChannelContext();
        $result = $this->source->resolveSpecificationData('', new Request(), $context);

        static::assertSame([], $result->dataRequirements);
    }

    #[TestDox('resolves cache tags using header section tag')]
    public function testResolveCacheTagsUsesHeaderSectionTag(): void
    {
        $layoutId = Uuid::randomHex();
        $assignment = static::createStub(ContentLayoutAssignmentInterface::class);
        $assignment->method('getContentLayoutId')->willReturn($layoutId);

        $this->resolver->method('resolve')->willReturn($assignment);

        $context = Generator::generateSalesChannelContext();
        $result = $this->source->resolveCacheTags('', new Request(), $context);

        static::assertSame([ContentSection::HEADER->buildLayoutTag($layoutId)], $result);
    }

    #[TestDox('throws when resolver returns null')]
    public function testThrowsLayoutAssignmentNotFoundWhenResolverReturnsNull(): void
    {
        $this->resolver->method('resolve')->willReturn(null);

        $context = Generator::generateSalesChannelContext();

        $this->expectExceptionObject(ContentSystemException::layoutAssignmentNotFound(
            'header',
            '',
            $context->getSalesChannel()->getId()
        ));

        $this->source->resolveLayoutId('', new Request(), $context);
    }
}
