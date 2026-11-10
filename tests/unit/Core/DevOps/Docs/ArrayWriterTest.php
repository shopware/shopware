<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\Docs;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Docs\ArrayWriter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @see \Shopware\Tests\Integration\Core\DevOps\Docs\ArrayWriterTest for the dumped files
 */
#[Package('framework')]
#[CoversClass(ArrayWriter::class)]
class ArrayWriterTest extends TestCase
{
    public function testSetAndGet(): void
    {
        $writer = new ArrayWriter('/dev/null');
        $writer->set('foo', 'bar');
        static::assertSame('bar', $writer->get('foo'));
    }

    public function testGetThrowsIfKeyMissing(): void
    {
        $writer = new ArrayWriter('/dev/null');
        $this->expectException(\InvalidArgumentException::class);
        $writer->get('missing');
    }

    public function testEnsure(): void
    {
        $writer = new ArrayWriter('/dev/null');
        $writer->ensure('foo');
        static::assertSame('__EMPTY__', $writer->get('foo'));
        // Should not overwrite existing
        $writer->set('foo', 'bar');
        $writer->ensure('foo');
        static::assertSame('bar', $writer->get('foo'));
    }

    public function testConstructorLoadsExistingFile(): void
    {
        $writer = new ArrayWriter(__DIR__ . '/_fixtures/existing-array.php');
        static::assertSame('bar', $writer->get('foo'));
    }
}
