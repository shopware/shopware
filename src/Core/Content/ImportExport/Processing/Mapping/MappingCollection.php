<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Mapping;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @extends Collection<Mapping>
 *
 * @phpstan-import-type MappingArray from Mapping
 */
#[Package('system-settings')]
class MappingCollection extends Collection
{
    /**
     * @var array<string, string>
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
        $mappingKey = $mapping->getKey();
        if (empty($mappingKey)) {
            // prevent collision with multiple not mapped mappings (key = '').
            // there is no direct lookup needed for these, but they should be stored and not overridden!
            $mappingKey = Uuid::randomHex();
        }

        parent::set($mappingKey, $mapping);
        $this->reverseIndex[$mapping->getMappedKey()] = $mappingKey;
    }

    public function getMapped(string $readKey): ?Mapping
    {
        if (!\array_key_exists($readKey, $this->reverseIndex)) {
            return null;
        }

        $writeKey = $this->reverseIndex[$readKey];

        return $this->get($writeKey);
    }

    /**
     * @param iterable<string|MappingArray|Mapping|MappingCollection> $data
     */
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

    /**
     * @return array<Mapping>
     */
    public function sortByPosition(): array
    {
        $mappings = $this->getElements();

        usort($mappings, fn (Mapping $firstMapping, Mapping $secondMapping) => $firstMapping->getPosition() - $secondMapping->getPosition());

        return $mappings;
    }

    protected function getExpectedClass(): ?string
    {
        return Mapping::class;
    }
}
