<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SeoUrlRouteConfig::class)]
class SeoUrlRouteConfigTest extends TestCase
{
    public function testConfig(): void
    {
        $entityDefinition = $this->createMock(EntityDefinition::class);
        $config = new SeoUrlRouteConfig(
            $entityDefinition,
            'foo_bar',
            '{{ foo.bar }}',
            false,
            static fn () => 'foo_bar_baz'
        );

        static::assertSame($entityDefinition, $config->getDefinition());
        static::assertSame('foo_bar', $config->getRouteName());
        static::assertSame('{{ foo.bar }}', $config->getTemplate());
        static::assertFalse($config->getSkipInvalid());
        static::assertSame('foo_bar_baz', $config->getRouteBySalesChannel(new SalesChannelEntity()));
    }
}
