<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Mapping;

use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @extends Collection<Mapping>
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
        $key = $mapping->getKey();
        if (empty($key)) {
            // prevent collision with multiple not mapped mappings (key = '').
            // there is no direct lookup needed for these, but they should be stored and not overridden!
            $key = Uuid::randomHex();
        }

        parent::set($key, $mapping);
        $this->reverseIndex[$mapping->getMappedKey()] = $key;
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

    public function sortByPosition(): array
    {
        $mappings = $this->getElements();

        usort($mappings, function (Mapping $firstMapping, Mapping $secondMapping) {
            return $firstMapping->getPosition() - $secondMapping->getPosition();
        });

        return $mappings;
    }
}
