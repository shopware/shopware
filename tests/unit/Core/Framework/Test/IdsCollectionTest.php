<?php

namespace Shopware\Tests\Unit\Core\Framework\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Uuid\Uuid;

class IdsCollectionTest extends TestCase
{
    public function testIdsCollection(): void
    {
        $ids = new IdsCollection();
        $id = $ids->create('test');

        $ids->set('foo', $id);

        static::assertEquals($id, $ids->get('foo'));
        static::assertIsString($id);
        static::assertEquals($id, $ids->get('test'));
        static::assertEquals([$id], array_values($ids->getList(['test'])));
        static::assertEquals([['id' => $id]], $ids->getIdArray(['test']));
        static::assertEquals(Uuid::fromHexToBytes($id), $ids->getBytes('test'));
        static::assertEquals([['id' => $id]], $ids->getIdArray(['test']));
    }
}
