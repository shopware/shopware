<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Theme\AbstractResolvedConfigLoader;
use Shopware\Storefront\Theme\ThemeConfigValueAccessor;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(ThemeConfigValueAccessor::class)]
class ThemeConfigValueAccessorTest extends TestCase
{
    protected function setUp(): void
    {
        Feature::skipTestIfActive('cache_rework', $this);
    }

    public function testBuildName(): void
    {
        static::assertEquals(
            'theme.foo',
            ThemeConfigValueAccessor::buildName('foo')
        );
    }

    public function testGetDisabledFineGrainedCaching(): void
    {
        $themeConfigLoader = $this->createMock(AbstractResolvedConfigLoader::class);
        $themeConfigLoader->expects(static::once())
            ->method('load')
            ->willReturn(['foo' => 'bar']);

        $themeConfigValueAccessor = new ThemeConfigValueAccessor(
            $themeConfigLoader,
            false,
            new EventDispatcher()
        );

        $context = $this->createMock(SalesChannelContext::class);
        $themeId = Uuid::randomHex();

        $themeConfigValueAccessor->trace('all', function () use ($themeConfigValueAccessor, $context, $themeId): void {
            static::assertEquals(
                'bar',
                $themeConfigValueAccessor->get('foo', $context, $themeId)
            );
        });

        static::assertSame(
            [
                'shopware.theme',
            ],
            $themeConfigValueAccessor->getTrace('all')
        );
    }

    public function testGetEnabledFineGrained(): void
    {
        $themeConfigValueAccessor = new ThemeConfigValueAccessor(
            $this->createMock(AbstractResolvedConfigLoader::class),
            true,
            new EventDispatcher()
        );

        $context = $this->createMock(SalesChannelContext::class);
        $themeId = Uuid::randomHex();

        $themeConfigValueAccessor->trace('all', function () use ($themeConfigValueAccessor, $context, $themeId): void {
            $themeConfigValueAccessor->get('foo', $context, $themeId);
        });

        static::assertSame(
            [
                'theme.foo',
            ],
            $themeConfigValueAccessor->getTrace('all')
        );
    }
}
