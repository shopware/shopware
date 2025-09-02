<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\DataTransfer\Metadata;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @internal
 *
 * @extends Collection<MetadataEntry>
 */
#[Package('discovery')]
class MetadataCollection extends Collection
{
    /**
     * @param list<MetadataEntry> $elements
     */
    public function __construct(
        array $elements = [],
    ) {
        $elements = $this->getIndexedList($elements);
        parent::__construct($elements);
    }

    /**
     * @description Adds the given MetadataEntry if no entry exists for the same locale,
     * or if the given entry has a different timestamp than the existing one.
     */
    public function addIfRequired(MetadataEntry $remoteEntry): void
    {
        $localEntry = $this->get($remoteEntry->locale);

        if ($localEntry && $this->isUpToDate($localEntry, $remoteEntry)) {
            return;
        }

        $remoteEntry->markForUpdate();
        $this->elements[$remoteEntry->locale] = $remoteEntry;
    }

    public function toJson(): string
    {
        $data = array_map(function (MetadataEntry $entry) {
            $serialized = $entry->jsonSerialize();
            unset($serialized['isUpdateRequired']);

            return $serialized;
        }, $this->elements);

        return json_encode($data, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT);
    }

    /**
     * @return list<string>
     */
    public function getLocalesRequiringUpdate(): array
    {
        $elements = $this->filter(fn (MetadataEntry $entry) => $entry->isUpdateRequired);

        return array_keys($elements->getElements());
    }

    protected function getExpectedClass(): string
    {
        return MetadataEntry::class;
    }

    private function isUpToDate(MetadataEntry $localEntry, MetadataEntry $remoteEntry): bool
    {
        return $localEntry->updatedAt->getTimestamp() === $remoteEntry->updatedAt->getTimestamp();
    }

    /**
     * @param list<MetadataEntry> $elements
     *
     * @return array<string, MetadataEntry>
     */
    private function getIndexedList(array $elements): array
    {
        $indexed = [];
        foreach ($elements as $element) {
            $indexed[$element->locale] = $element;
        }

        return $indexed;
    }
}
