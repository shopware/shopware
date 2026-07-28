<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Telemetry\EntityGroupResolver;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityGroupResolver::class)]
class EntityGroupResolverTest extends TestCase
{
    #[DataProvider('entityProvider')]
    public function testResolve(string $entityName, string $expected): void
    {
        static::assertSame($expected, (new EntityGroupResolver())->resolve($entityName));
    }

    public static function entityProvider(): \Generator
    {
        // exact full-name lookup takes precedence over root-token mapping
        yield 'main_category maps to category' => ['main_category', 'category'];
        yield 'property_group maps to product' => ['property_group', 'product'];
        yield 'property_group_option maps to product' => ['property_group_option', 'product'];

        // root-token (part before first underscore) mapping
        yield 'product_price falls back to product root' => ['product_price', 'product'];
        yield 'newsletter_recipient maps to customer' => ['newsletter_recipient', 'customer'];
        yield 'mail_template maps to content' => ['mail_template', 'content'];
        yield 'landing_page maps to content' => ['landing_page', 'content'];
        yield 'sales_channel maps to system' => ['sales_channel', 'system'];

        // whole name used as root token when there is no underscore
        yield 'product without suffix maps to product' => ['product', 'product'];

        // unlisted roots fall through to other
        yield 'unknown entity is other' => ['totally_unknown', 'other'];
        yield 'unknown single-token entity is other' => ['unknown', 'other'];
        yield 'plugin custom entity is other' => ['custom_entity_blog', 'other'];
    }
}
