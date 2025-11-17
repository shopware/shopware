<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Field\Flag;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\AdminSalesChannelApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Api\Context\ShopApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(ApiAware::class)]
#[Package('framework')]
class ApiAwareTest extends TestCase
{
    public function testDefaultAllowsBothApis(): void
    {
        $flag = new ApiAware();

        static::assertTrue($flag->isBaseUrlAllowed('/api'));
        static::assertTrue($flag->isBaseUrlAllowed('/store-api'));

        static::assertTrue($flag->isSourceAllowed(AdminApiSource::class));
        static::assertTrue($flag->isSourceAllowed(SalesChannelApiSource::class));
        static::assertTrue($flag->isSourceAllowed(ShopApiSource::class));
        static::assertTrue($flag->isSourceAllowed(AdminSalesChannelApiSource::class));
        static::assertTrue($flag->isSourceAllowed(SystemSource::class));
    }

    public function testOnlyAdminApiAware(): void
    {
        $flag = new ApiAware(AdminApiSource::class);

        static::assertTrue($flag->isBaseUrlAllowed('/api'));
        static::assertFalse($flag->isBaseUrlAllowed('/store-api'));

        static::assertTrue($flag->isSourceAllowed(AdminApiSource::class));
        static::assertFalse($flag->isSourceAllowed(SalesChannelApiSource::class));
        static::assertFalse($flag->isSourceAllowed(ShopApiSource::class));
        static::assertFalse($flag->isSourceAllowed(AdminSalesChannelApiSource::class));
        static::assertTrue($flag->isSourceAllowed(SystemSource::class));
    }

    public function testOnlyStoreApiAware(): void
    {
        $flag = new ApiAware(SalesChannelApiSource::class);

        static::assertFalse($flag->isBaseUrlAllowed('/api'));
        static::assertTrue($flag->isBaseUrlAllowed('/store-api'));

        static::assertFalse($flag->isSourceAllowed(AdminApiSource::class));
        static::assertTrue($flag->isSourceAllowed(SalesChannelApiSource::class));
        static::assertTrue($flag->isSourceAllowed(ShopApiSource::class));
        static::assertTrue($flag->isSourceAllowed(AdminSalesChannelApiSource::class));
        static::assertTrue($flag->isSourceAllowed(SystemSource::class));
    }

    public function testWithoutDescription(): void
    {
        $flag = new ApiAware();

        static::assertNull($flag->getDescription());
    }

    public function testWithDescription(): void
    {
        $description = 'Main product image displayed in listings and detail pages';
        $flag = (new ApiAware(SalesChannelApiSource::class))->withDescription($description);

        static::assertSame($description, $flag->getDescription());
        static::assertTrue($flag->isSourceAllowed(SalesChannelApiSource::class));
    }

    public function testDescriptionInParse(): void
    {
        $description = 'Test description';
        $flag = (new ApiAware(SalesChannelApiSource::class))->withDescription($description);

        $result = iterator_to_array($flag->parse());

        static::assertArrayHasKey('description', $result);
        static::assertSame($description, $result['description']);
        static::assertArrayHasKey('read_protected', $result);
    }

    public function testParseWithoutDescription(): void
    {
        $flag = new ApiAware(SalesChannelApiSource::class);

        $result = iterator_to_array($flag->parse());

        static::assertArrayNotHasKey('description', $result);
        static::assertArrayHasKey('read_protected', $result);
    }

    public function testDescriptionWithMultipleSources(): void
    {
        $description = 'Available in both admin and store API';
        $flag = (new ApiAware(AdminApiSource::class, SalesChannelApiSource::class))->withDescription($description);

        static::assertSame($description, $flag->getDescription());
        static::assertTrue($flag->isSourceAllowed(AdminApiSource::class));
        static::assertTrue($flag->isSourceAllowed(SalesChannelApiSource::class));
    }

    public function testFluentApiReturnsInstance(): void
    {
        $flag = new ApiAware(SalesChannelApiSource::class);
        $result = $flag->withDescription('Test');

        static::assertSame($flag, $result);
        static::assertSame('Test', $flag->getDescription());
    }
}
