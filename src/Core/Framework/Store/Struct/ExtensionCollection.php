<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @codeCoverageIgnore
 */
class ExtensionCollection extends Collection
{
    /**
     * @var int
     */
    private $total = 0;

    public function getTotal(): int
    {
        return $this->total;
    }

    public function setTotal(int $total): void
    {
        $this->total = $total;
    }

    public function merge(self $collection): self
    {
        /** @var ExtensionStruct $entity */
        foreach ($collection as $entity) {
            if ($this->has($entity->getId())) {
                continue;
            }
            $this->add($entity);
        }

        return $this;
    }

    protected function getExpectedClass(): ?string
    {
        return ExtensionStruct::class;
    }
}
