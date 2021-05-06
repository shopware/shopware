<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Struct;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Shopware\Core\Framework\Store\Struct\PermissionCollection;

class ExtensionStructTest extends TestCase
{
    public function testItCategorizesThePermissionCollectionWhenStructIsSerialized(): void
    {
        $detailData = $this->getDetailFixture();
        $detailData['permissions'] = new PermissionCollection($detailData['permissions']);

        $extension = ExtensionStruct::fromArray($detailData);

        static::assertInstanceOf(PermissionCollection::class, $extension->getPermissions());

        $serializedExtension = json_decode(json_encode($extension), true);
        $categorizedPermissions = $serializedExtension['permissions'];

        static::assertCount(3, $categorizedPermissions);
        static::assertEquals([
            'product',
            'promotion',
            'other',
        ], array_keys($categorizedPermissions));
    }

    private function getDetailFixture(): array
    {
        $content = file_get_contents(__DIR__ . '/../_fixtures/responses/extension-detail.json');

        return json_decode($content, true);
    }
}
