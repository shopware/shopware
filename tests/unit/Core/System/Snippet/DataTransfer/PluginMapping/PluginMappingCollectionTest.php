<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\DataTransfer\PluginMapping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\DataTransfer\PluginMapping\PluginMapping;
use Shopware\Core\System\Snippet\DataTransfer\PluginMapping\PluginMappingCollection;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(PluginMappingCollection::class)]
class PluginMappingCollectionTest extends TestCase
{
    public function testAddKeysTheMappingByPluginName(): void
    {
        $mapping = new PluginMapping('SwagExample', 'swag-example');
        $collection = new PluginMappingCollection();

        $collection->add($mapping);

        static::assertSame($mapping, $collection->get('SwagExample'));
    }

    public function testSetIgnoresTheGivenKeyInFavourOfThePluginName(): void
    {
        $mapping = new PluginMapping('SwagExample', 'swag-example');
        $collection = new PluginMappingCollection();

        $collection->set('something-else', $mapping);

        static::assertNull($collection->get('something-else'));
        static::assertSame($mapping, $collection->get('SwagExample'));
    }
}
