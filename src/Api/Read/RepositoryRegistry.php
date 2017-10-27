<?php declare(strict_types=1);

namespace Shopware\Api\Read;

use Shopware\Api\RepositoryInterface;
use Shopware\Framework\Struct\Collection;

class RepositoryRegistry extends Collection
{
    /**
     * @var RepositoryInterface[]
     */
    protected $elements = [];

    public function __construct(iterable $elements = [])
    {
        foreach ($elements as $element) {
            $this->add($element);
        }
    }

    public function add(RepositoryInterface $repository): void
    {
        $key = $this->getKey($repository);
        $this->elements[$key] = $repository;
    }

    public function exists(RepositoryInterface $repository): bool
    {
        return parent::has($this->getKey($repository));
    }

    public function getList(array $entityNames): RepositoryRegistry
    {
        return new self(array_intersect_key($this->elements, array_flip($entityNames)));
    }

    public function get(string $entityName): ? RepositoryInterface
    {
        if ($this->has($entityName)) {
            return $this->elements[$entityName];
        }

        return null;
    }

    public function getEntityNames(): array
    {
        return $this->fmap(function (RepositoryInterface $repository) {
            return $repository->getEntityName();
        });
    }

    public function current(): RepositoryInterface
    {
        return parent::current();
    }

    protected function getKey(RepositoryInterface $repository): string
    {
        return $repository->getEntityName();
    }
}
