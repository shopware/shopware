<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
interface AssignArrayInterface
{
    /**
     * @param array<array-key, mixed> $options
     *
     * @return $this
     */
    public function assign(array $options);

    /**
     * @param array<array-key, mixed> $options
     */
    public function assignRecursive(array $options): static;
}
