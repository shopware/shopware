<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Session\StatefulFlashBag;

/**
 * @internal
 *
 * @see https://github.com/symfony/symfony/blob/8.1/src/Symfony/Component/HttpFoundation/Tests/Session/Flash/FlashBagTest.php
 */
#[CoversClass(StatefulFlashBag::class)]
class StatefulFlashBagTest extends TestCase
{
    /**
     * @var array<string, list<string>>
     */
    protected array $array = [];

    private StatefulFlashBag $bag;

    protected function setUp(): void
    {
        $this->bag = new StatefulFlashBag();
        $this->array = ['notice' => ['A previous flash message']];
        $this->bag->initialize($this->array);
    }

    protected function tearDown(): void
    {
        unset($this->bag);
    }

    public function testInitialize(): void
    {
        $bag = new StatefulFlashBag();
        $bag->initialize($this->array);
        static::assertEquals($this->array, $bag->peekAll());
        $array = ['should' => ['change']];
        $bag->initialize($array);
        static::assertEquals($array, $bag->peekAll());
    }

    public function testPeek(): void
    {
        static::assertEquals([], $this->bag->peek('non_existing'));
        static::assertEquals(['default'], $this->bag->peek('not_existing', ['default']));
        static::assertEquals(['A previous flash message'], $this->bag->peek('notice'));
        static::assertEquals(['A previous flash message'], $this->bag->peek('notice'));

        static::assertTrue($this->bag->hasAnyFlashes());
        static::assertFalse($this->bag->displayedAnyFlashes());
    }

    public function testAdd(): void
    {
        $tab = ['bar' => 'baz'];
        $this->bag->add('string_message', 'lorem');
        $this->bag->add('object_message', new \stdClass());
        $this->bag->add('array_message', $tab);

        static::assertEquals(['lorem'], $this->bag->get('string_message'));
        static::assertEquals([new \stdClass()], $this->bag->get('object_message'));
        static::assertEquals([$tab], $this->bag->get('array_message'));
    }

    public function testGet(): void
    {
        static::assertEquals([], $this->bag->get('non_existing'));
        static::assertFalse($this->bag->displayedAnyFlashes());
        static::assertTrue($this->bag->hasAnyFlashes());

        static::assertEquals(['default'], $this->bag->get('not_existing', ['default']));
        static::assertFalse($this->bag->displayedAnyFlashes());
        static::assertTrue($this->bag->hasAnyFlashes());

        static::assertEquals(['A previous flash message'], $this->bag->get('notice'));
        static::assertTrue($this->bag->displayedAnyFlashes());
        static::assertFalse($this->bag->hasAnyFlashes());

        static::assertEquals([], $this->bag->get('notice'));
        static::assertTrue($this->bag->displayedAnyFlashes());
        static::assertFalse($this->bag->hasAnyFlashes());
    }

    public function testAll(): void
    {
        $this->bag->set('notice', 'Foo');
        $this->bag->set('error', 'Bar');
        static::assertFalse($this->bag->displayedAnyFlashes());
        static::assertTrue($this->bag->hasAnyFlashes());

        static::assertEquals(
            [
                'notice' => ['Foo'],
                'error' => ['Bar'], ],
            $this->bag->all()
        );
        static::assertTrue($this->bag->displayedAnyFlashes());
        static::assertFalse($this->bag->hasAnyFlashes());

        static::assertEquals([], $this->bag->all());
        static::assertFalse($this->bag->hasAnyFlashes());
        static::assertTrue($this->bag->displayedAnyFlashes());
    }

    public function testSet(): void
    {
        $this->bag->set('notice', 'Foo');
        $this->bag->set('notice', 'Bar');
        static::assertEquals(['Bar'], $this->bag->peek('notice'));
    }

    public function testHas(): void
    {
        static::assertFalse($this->bag->has('nothing'));
        static::assertTrue($this->bag->has('notice'));
    }

    public function testKeys(): void
    {
        static::assertEquals(['notice'], $this->bag->keys());
    }

    public function testSetAll(): void
    {
        $this->bag->add('one_flash', 'Foo');
        $this->bag->add('another_flash', 'Bar');
        static::assertTrue($this->bag->has('one_flash'));
        static::assertTrue($this->bag->has('another_flash'));
        $this->bag->setAll(['unique_flash' => 'FooBar']);
        static::assertFalse($this->bag->has('one_flash'));
        static::assertFalse($this->bag->has('another_flash'));
        static::assertSame(['unique_flash' => 'FooBar'], $this->bag->all());
        static::assertSame([], $this->bag->all());
    }

    public function testPeekAll(): void
    {
        $this->bag->set('notice', 'Foo');
        $this->bag->set('error', 'Bar');
        static::assertEquals(
            [
                'notice' => ['Foo'],
                'error' => ['Bar'],
            ],
            $this->bag->peekAll()
        );
        static::assertTrue($this->bag->has('notice'));
        static::assertTrue($this->bag->has('error'));
        static::assertEquals(
            [
                'notice' => ['Foo'],
                'error' => ['Bar'],
            ],
            $this->bag->peekAll()
        );

        static::assertTrue($this->bag->hasAnyFlashes());
        static::assertFalse($this->bag->displayedAnyFlashes());
    }
}
