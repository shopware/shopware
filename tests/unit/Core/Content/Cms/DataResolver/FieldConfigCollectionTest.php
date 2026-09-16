<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\DataResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(FieldConfigCollection::class)]
class FieldConfigCollectionTest extends TestCase
{
    public function testAddKeysTheConfigByItsName(): void
    {
        $config = new FieldConfig('title', FieldConfig::SOURCE_STATIC, 'Hello');
        $collection = new FieldConfigCollection();

        $collection->add($config);

        static::assertSame($config, $collection->get('title'));
    }

    public function testSetIgnoresTheGivenKeyInFavourOfTheName(): void
    {
        $config = new FieldConfig('title', FieldConfig::SOURCE_STATIC, 'Hello');
        $collection = new FieldConfigCollection();

        $collection->set('something-else', $config);

        static::assertNull($collection->get('something-else'));
        static::assertSame($config, $collection->get('title'));
    }

    public function testApiAlias(): void
    {
        static::assertSame('cms_data_resolver_field_config_collection', (new FieldConfigCollection())->getApiAlias());
    }
}
