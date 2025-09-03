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
    public function set($key, $element): void
    {
        parent::set($element->locale, $element);
    }

    public function add($element): void
    {
        $this->validateType($element);
        parent::set($element->locale, $element);
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

    public function jsonSerialize(): array
    {
        return array_map(function (MetadataEntry $entry) {
            $serialized = $entry->jsonSerialize();
            unset($serialized['isUpdateRequired']);

            return $serialized;
        }, $this->elements);
    }

    /**
     * @return list<string>
     */
    public function getLocalesRequiringUpdate(): array
    {
        return $this->filter(fn (MetadataEntry $entry) => $entry->isUpdateRequired)->getKeys();
    }

    protected function getExpectedClass(): string
    {
        return MetadataEntry::class;
    }

    private function isUpToDate(MetadataEntry $localEntry, MetadataEntry $remoteEntry): bool
    {
        return $localEntry->updatedAt->getTimestamp() === $remoteEntry->updatedAt->getTimestamp();
    }
}
