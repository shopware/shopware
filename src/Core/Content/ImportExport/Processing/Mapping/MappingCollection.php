<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Mapping;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method Mapping[]    getIterator()
 * @method Mapping[]    getElements()
 * @method Mapping|null get(string $key)
 * @method Mapping|null first()
 * @method Mapping|null last()
 */
class MappingCollection extends Collection
{
    /**
     * @var array
     */
    protected $reverseIndex = [];

    /**
     * @param Mapping $mapping
     */
    public function add($mapping): void
    {
        $this->validateType($mapping);
        $this->set($mapping->getKey(), $mapping);
    }

    /**
     * @param string  $key
     * @param Mapping $mapping
     */
    public function set($key, $mapping): void
    {
        $this->validateType($mapping);
        parent::set($mapping->getKey(), $mapping);
        $this->reverseIndex[$mapping->getMappedKey()] = $mapping->getKey();
    }

    public function getMapped(string $readKey): ?Mapping
    {
        if (!\array_key_exists($readKey, $this->reverseIndex)) {
            return null;
        }

        $writeKey = $this->reverseIndex[$readKey];

        return $this->get($writeKey);
    }

    public function getExpectedClass(): ?string
    {
        return Mapping::class;
    }

    public static function fromIterable(iterable $data): self
    {
        if ($data instanceof MappingCollection) {
            return $data;
        }

        $mappingCollection = new self();

        foreach ($data as $mapping) {
            if (\is_string($mapping)) {
                $mapping = new Mapping($mapping);
            } elseif (\is_array($mapping)) {
                $mapping = Mapping::fromArray($mapping);
            }

            if ($mapping instanceof Mapping) {
                $mappingCollection->add($mapping);
            }
        }

        return $mappingCollection;
    }
}
