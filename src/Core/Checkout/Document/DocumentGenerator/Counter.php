<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\DocumentGenerator;

class Counter
{
    /**
     * @var int
     */
    private $counter = 0;

    /**
     * @var int
     */
    private $page = 1;

    public function getCounter(): int
    {
        return $this->counter;
    }

    public function increment(): void
    {
        $this->counter = $this->counter + 1;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function incrementPage(): void
    {
        $this->page = $this->page + 1;
    }
}
