<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Script\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Script\Facade\ArrayFacade;

/**
 * @internal
 */
class ArrayFacadeTest extends TestCase
{
    public function testAssignment(): void
    {
        $array = [1, 2, 3];

        $functions = new ArrayFacade($array);

        $functions[] = 4;
        $functions[] = 'string';
        $functions[] = 'string-2';
        $functions[] = true;
        $functions[] = false;
        $functions[] = 3.2;

        static::assertContains(1, $functions);
        static::assertContains(2, $functions);
        static::assertContains(3, $functions);
        static::assertContains(4, $functions);
        static::assertContains('string', $functions);
        static::assertContains('string-2', $functions);
        static::assertContains(true, $functions);
        static::assertContains(false, $functions);
        static::assertContains(3.2, $functions);
    }

    public function testLoop(): void
    {
        $initial = [1, 2, 3];
        $functions = new ArrayFacade($initial);
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
        $a = new ArrayFacade($aArray);
        $b = new ArrayFacade($bArray);
        $b->merge($a);

        static::assertContains(3, $b);
        static::assertContains(4, $b);
    }

    public function testReplace(): void
    {
        $aArray = ['foo' => 'bar'];

        $a = new ArrayFacade($aArray);
        $a->replace(['foo' => 'baz']);

        static::assertEquals('baz', $a['foo']);
    }
}
