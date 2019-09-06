<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Iterator;

use Symfony\Component\Console\Helper\ProgressBar;

class ProgressBarIterator implements \Iterator
{
    /**
     * @var \Iterator
     */
    private $inner;

    /**
     * @var ProgressBar
     */
    private $progressBar;

    public function __construct(\Iterator $inner, ProgressBar $progressBar)
    {
        $this->inner = $inner;
        $this->progressBar = $progressBar;
    }

    public function current()
    {
        return $this->inner->current();
    }

    public function next(): void
    {
        $this->inner->next();
        $this->progressBar->advance();
    }

    public function key()
    {
        return $this->inner->key();
    }

    public function valid()
    {
        $valid = $this->inner->valid();
        if (!$valid) {
            $this->progressBar->finish();
        }

        return $valid;
    }

    public function rewind(): void
    {
        $this->inner->rewind();
        $this->progressBar->setProgress(0);
    }
}
