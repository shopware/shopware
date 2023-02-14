<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Struct;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Shopware\Core\Framework\Store\Struct\PermissionCollection;

/**
 * @internal
 */
class ExtensionStructTest extends TestCase
{
    public function testFromArray(): void
    {
        $detailData = $this->getDetailFixture();
        $struct = ExtensionStruct::fromArray($detailData);

        static::assertInstanceOf(ExtensionStruct::class, $struct);
    }

    /**
     * @dataProvider badValuesProvider
     */
    public function testItThrowsOnMissingData(array $badValues): void
    {
        static::expectException(\InvalidArgumentException::class);
        ExtensionStruct::fromArray($badValues);
    }

    public function testItCategorizesThePermissionCollectionWhenStructIsSerialized(): void
    {
        $detailData = $this->getDetailFixture();
        $detailData['permissions'] = new PermissionCollection($detailData['permissions']);

        $extension = ExtensionStruct::fromArray($detailData);

        static::assertInstanceOf(PermissionCollection::class, $extension->getPermissions());

        $serializedExtension = json_decode(json_encode($extension, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);
        $categorizedPermissions = $serializedExtension['permissions'];

        static::assertCount(3, $categorizedPermissions);
        static::assertEquals([
            'product',
            'promotion',
            'other',
        ], array_keys($categorizedPermissions));
    }

    public static function badValuesProvider(): iterable
    {
        yield [[]];
        yield [['name' => 'foo']];
        yield [['type' => 'foo']];
        yield [['name' => 'foo', 'label' => 'bar']];
        yield [['label' => 'bar', 'type' => 'foobar']];
    }

    private function getDetailFixture(): array
    {
        $content = file_get_contents(__DIR__ . '/../_fixtures/responses/extension-detail.json');

        return json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
    }
}
