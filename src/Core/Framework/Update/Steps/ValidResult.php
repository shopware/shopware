<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Steps;

use Shopware\Core\Framework\Log\Package;

#[Package('system-settings')]
class ValidResult
{
    /**
     * @param array<mixed> $args
     */
    public function __construct(private int $offset, private int $total, private array $args = [])
    {
    }

    /**
     * @return array<mixed>
     */
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
