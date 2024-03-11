<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Command\Scaffolding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Stub;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection;

/**
 * @internal
 */
#[CoversClass(StubCollection::class)]
class StubCollectionTest extends TestCase
{
    public function testConstructor(): void
    {
        $stubs = [
            Stub::raw('/path/to/stub1', 'Content 1'),
            Stub::raw('/path/to/stub2', 'Content 2'),
        ];

        $collection = new StubCollection($stubs);

        static::assertCount(2, $collection);
        static::assertEquals($stubs[0], $collection->get('/path/to/stub1'));
        static::assertEquals($stubs[1], $collection->get('/path/to/stub2'));
    }

    public function testAdd(): void
    {
        $collection = new StubCollection();

        $stub = Stub::raw('/path/to/stub', 'Content');

        $collection->add($stub);

        static::assertCount(1, $collection);
        static::assertEquals($stub, $collection->get('/path/to/stub'));
    }

    public function testAppendNewStub(): void
    {
        $collection = new StubCollection();
        $path = '/path/to/stub';
        $content = 'Content';

        $collection->append($path, $content);

        static::assertCount(1, $collection);
        static::assertInstanceOf(Stub::class, $collection->get('/path/to/stub'));
        static::assertEquals($path, $collection->get('/path/to/stub')->getPath());
        static::assertEquals($content, $collection->get('/path/to/stub')->getContent());
    }

    public function testAppendExistingStub(): void
    {
        $collection = new StubCollection();
        $path = '/path/to/stub';
        $initialContent = 'Initial Content';
        $appendedContent = 'Appended Content';

        $collection->append($path, $initialContent);
        $collection->append($path, $appendedContent);

        static::assertCount(1, $collection);
        static::assertInstanceOf(Stub::class, $collection->get('/path/to/stub'));
        static::assertEquals($path, $collection->get('/path/to/stub')->getPath());
        static::assertEquals($initialContent . $appendedContent, $collection->get('/path/to/stub')->getContent());
    }
}
