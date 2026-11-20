<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Adapter\NoneSpecificationSource;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NoneSpecificationSource::class)]
class NoneSpecificationSourceTest extends TestCase
{
    #[TestDox('exposes no root-ambient context')]
    public function testExposesNoRootContext(): void
    {
        static::assertSame([], (new NoneSpecificationSource())->providedRootContext(Context::createDefaultContext()));
    }

    #[TestDox('claims no path')]
    public function testClaimsNoPath(): void
    {
        $source = new NoneSpecificationSource();

        static::assertFalse($source->supports('/anything', new Request(), static::createStub(SalesChannelContext::class)));
    }

    #[TestDox('resolves no entity type')]
    public function testResolvesNoEntityType(): void
    {
        static::assertFalse((new NoneSpecificationSource())->supportsEntityType('product'));
    }

    /**
     * @param \Closure(NoneSpecificationSource, Request, SalesChannelContext): mixed $invoke
     */
    #[DataProvider('resolutionMethodsFailHardProvider')]
    #[TestDox('fails hard when an unreachable resolution method is called')]
    public function testResolutionMethodsFailHard(\Closure $invoke): void
    {
        $this->expectExceptionObject(ContentSystemException::noneSourceNotRenderable());

        $invoke(new NoneSpecificationSource(), new Request(), static::createStub(SalesChannelContext::class));
    }

    /**
     * @return iterable<string, array{\Closure(NoneSpecificationSource, Request, SalesChannelContext): mixed}>
     */
    public static function resolutionMethodsFailHardProvider(): iterable
    {
        yield 'resolveLayoutId' => [static fn (NoneSpecificationSource $s, Request $r, SalesChannelContext $c) => $s->resolveLayoutId('/path', $r, $c)];
        yield 'resolveSpecificationData' => [static fn (NoneSpecificationSource $s, Request $r, SalesChannelContext $c) => $s->resolveSpecificationData('/path', $r, $c)];
        yield 'resolveTargetElementId' => [static fn (NoneSpecificationSource $s, Request $r, SalesChannelContext $c) => $s->resolveTargetElementId('/path', $r, $c)];
        yield 'resolveCacheTags' => [static fn (NoneSpecificationSource $s, Request $r, SalesChannelContext $c) => $s->resolveCacheTags('/path', $r, $c)];
    }
}
