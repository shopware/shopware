<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Steps;

class ValidResult
{
    /**
     * @var int
     */
    private $offset;

    /**
     * @var int
     */
    private $total;

    /**
     * @var array
     */
    private $args;

    public function __construct(int $offset, int $total, array $args = [])
    {
        $this->offset = $offset;
        $this->total = $total;
        $this->args = $args;
    }

    public function getArgs(): array
    {
        return $this->args;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getTotal(): int
    {
        return $this->total;
    }
}
