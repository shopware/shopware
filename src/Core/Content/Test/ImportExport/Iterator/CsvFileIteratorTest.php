<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\Iterator;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Iterator\CsvFileIterator;

class CsvFileIteratorTest extends TestCase
{
    public function testSeekMethod(): void
    {
        $iterator = new CsvFileIterator($this->getTestData(5));

        $iterator->rewind();
        $iterator->seek(3);
        static::assertTrue($iterator->valid());
        static::assertSame(3, $iterator->key());
        static::assertSame('Line3', $iterator->current()['Header']);
    }

    private function getTestData(int $rows)
    {
        $stream = fopen('php://memory', 'r+b');
        fwrite($stream, "Header\n");
        for ($i = 0; $i < $rows; ++$i) {
            fwrite($stream, 'Line' . $i . "\n");
        }
        rewind($stream);

        return $stream;
    }
}
