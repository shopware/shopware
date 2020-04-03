<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Pipe;

use Shopware\Core\Content\ImportExport\Struct\Config;

class ChainPipe extends AbstractPipe
{
    /**
     * @var AbstractPipe[]
     */
    private $chain;

    public function __construct(array $pipeChain)
    {
        $this->chain = $pipeChain;
    }

    public function in(Config $config, iterable $record): iterable
    {
        $generator = $record;

        foreach ($this->chain as $pipe) {
            $generator = $pipe->in($config, $generator);
        }

        yield from $generator;
    }

    public function out(Config $config, iterable $record): iterable
    {
        $pipes = array_reverse($this->chain);

        $generator = $record;

        foreach ($pipes as $pipe) {
            $generator = $pipe->out($config, $generator);
        }

        yield from $generator;
    }
}
