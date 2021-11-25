<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Script\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Script\Service\ArrayFunctions;

class ArrayFunctionsTest extends TestCase
{
    public function testReferences(): void
    {
        $array = [1, 2, 3];

        $functions = new ArrayFunctions($array);

        $functions[] = 4;
        $functions[] = 'string';
        $functions[] = 'string-2';
        $functions[] = true;
        $functions[] = false;
        $functions[] = 3.2;

        static::assertContains(1, $array);
        static::assertContains(2, $array);
        static::assertContains(3, $array);
        static::assertContains(4, $array);
        static::assertContains('string', $array);
        static::assertContains('string-2', $array);
        static::assertContains(true, $array);
        static::assertContains(false, $array);
        static::assertContains(3.2, $array);
    }

    public function testLoop(): void
    {
        $initial = [1, 2, 3];
        $functions = new ArrayFunctions($initial);
        $f = [];
        foreach ($functions as $key => $value) {
            $f[$key] = $value;
        }

        static::assertContains(1, $f);
        static::assertContains(2, $f);
        static::assertContains(3, $f);
    }

    public function testMerge(): void
    {
        $aArray = [3, 4];
        $bArray = [];
        $a = new ArrayFunctions($aArray);
        $b = new ArrayFunctions($bArray);
        $b->merge($a);

        static::assertContains(3, $b);
        static::assertContains(4, $b);
    }

    public function testReplace(): void
    {
        $aArray = ['foo' => 'bar'];

        $a = new ArrayFunctions($aArray);
        $a->replace(['foo' => 'baz']);

        static::assertEquals('baz', $a['foo']);
    }

    public function testWithObjectProperty(): void
    {
        $object = new TestObject();
        $functions = new ArrayFunctions($object->array);

        $functions[] = 'test';

        static::assertContains('test', $object->getArray());
    }
}

class TestObject
{
    public array $array = [];

    public function getArray(): array
    {
        return $this->array;
    }

    public function setArray(array $array): void
    {
        $this->array = $array;
    }
}
