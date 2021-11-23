<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Script\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Script\Service\ArrayFunctions;

class ArrayFunctionsTest extends TestCase
{
    public function testFunctions(): void
    {
        $array = [1, 2, 3];

        $functions = new ArrayFunctions($array);

        $functions->add(4);
        $functions->add('string');
        $functions->add('string-2');
        $functions->add(true);
        $functions->add(false);
        $functions->add(3.2);

        static::assertTrue($functions->has(1));
        static::assertTrue($functions->has(2));
        static::assertTrue($functions->has(3));
        static::assertTrue($functions->has(4));
        static::assertTrue($functions->has('string'));
        static::assertTrue($functions->has('string-2'));
        static::assertTrue($functions->has(true));
        static::assertTrue($functions->has(false));
        static::assertTrue($functions->has(3.2));

        static::assertContains(1, $array);
        static::assertContains(2, $array);
        static::assertContains(3, $array);
        static::assertContains(4, $array);
        static::assertContains('string', $array);
        static::assertContains('string-2', $array);
        static::assertContains(true, $array);
        static::assertContains(false, $array);
        static::assertContains(3.2, $array);

        $functions->remove(4);
        $functions->remove('string');
        $functions->remove('string-2');
        $functions->remove(true);
        $functions->remove(false);
        $functions->remove(3.2);

        static::assertFalse($functions->has(4));
        static::assertFalse($functions->has('string'));
        static::assertFalse($functions->has('string-2'));
        static::assertFalse($functions->has(true));
        static::assertFalse($functions->has(false));
        static::assertFalse($functions->has(3.2));
    }

    public function testWithObjectProperty(): void
    {
        $object = new TestObject();

        $functions = new ArrayFunctions($object->array);

        $functions->add('test');

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
