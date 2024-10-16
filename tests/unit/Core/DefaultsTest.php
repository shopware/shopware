<?php

namespace Shopware\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;

class DefaultsTest extends TestCase
{
    public function testValues(): void
    {
        static::assertEquals('2fbb5fe2e29a4d70aa5854ce7ce3e20b', Defaults::LANGUAGE_SYSTEM);
        static::assertEquals('0fa91ce3e96a4bc2be4bd9ce752c3425', Defaults::LIVE_VERSION);
        static::assertEquals('b7d2554b0ce847cd82f3ac9bd1c0dfca', Defaults::CURRENCY);
        static::assertEquals('f183ee5650cf4bdb8a774337575067a6', Defaults::SALES_CHANNEL_TYPE_API);
        static::assertEquals('8a243080f92e4c719546314b577cf82b', Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        static::assertEquals('ed535e5722134ac1aa6524f73e26881b', Defaults::SALES_CHANNEL_TYPE_PRODUCT_COMPARISON);
        static::assertEquals('Y-m-d H:i:s.v', Defaults::STORAGE_DATE_TIME_FORMAT);
        static::assertEquals('Y-m-d', Defaults::STORAGE_DATE_FORMAT);
        static::assertEquals('7a6d253a67204037966f42b0119704d5', Defaults::CMS_PRODUCT_DETAIL_PAGE);
    }
}
