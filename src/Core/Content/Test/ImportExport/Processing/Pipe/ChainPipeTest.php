<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\Processing\Pipe;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Processing\Pipe\AbstractPipe;
use Shopware\Core\Content\ImportExport\Processing\Pipe\ChainPipe;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('system-settings')]
class ChainPipeTest extends TestCase
{
    public function testChainPipe(): void
    {
        $outerPipe = $this->createMock(AbstractPipe::class);
        $innerPipe = $this->createMock(AbstractPipe::class);

        $chainPipe = new ChainPipe([$outerPipe, $innerPipe]);

        $data = [
            'foo' => 'bar',
        ];
        $config = new Config([], [], []);

        $innerInCalled = false;

        $outerPipe->expects(static::once())->method('in')
            ->willReturnCallback(
                function (Config $c, $record) use ($config, $data, &$innerInCalled) {
                    $this->assertSame($config, $c);

                    $record = \is_array($record) ? $record : iterator_to_array($record);
                    $this->assertSame($data, $record);

                    static::assertFalse($innerInCalled, 'outer must be called first');

                    yield from $record;
                }
            );

        $innerPipe->expects(static::once())->method('in')
            ->willReturnCallback(
                function (Config $c, $record) use ($config, $data, &$innerInCalled) {
                    $this->assertSame($config, $c);

                    $record = \is_array($record) ? $record : iterator_to_array($record);
                    $this->assertSame($data, $record);

                    $innerInCalled = true;

                    yield from $record;
                }
            );

        $result = iterator_to_array($chainPipe->in($config, $data));
        static::assertSame($data, $result);

        $innerOutCalled = false;

        $outerPipe->expects(static::once())->method('out')
            ->willReturnCallback(
                function (Config $c, $record) use ($config, $data, &$innerOutCalled) {
                    $this->assertSame($config, $c);

                    $record = \is_array($record) ? $record : iterator_to_array($record);
                    $this->assertSame($data, $record);

                    static::assertTrue($innerOutCalled, 'outer must be called last');

                    yield from $record;
                }
            );

        $innerPipe->expects(static::once())->method('out')
            ->willReturnCallback(
                function (Config $c, $record) use ($config, $data, &$innerOutCalled) {
                    $this->assertSame($config, $c);

                    $record = \is_array($record) ? $record : iterator_to_array($record);
                    $this->assertSame($data, $record);

                    $innerOutCalled = true;

                    yield from $record;
                }
            );

        $result = iterator_to_array($chainPipe->out($config, $data));
        static::assertSame($data, $result);
    }
}
