<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Aggregate\CmsSlot;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<CmsSlotEntity>
 */
#[Package('content')]
class CmsSlotCollection extends EntityCollection
{
    /**
     * @var CmsSlotEntity[]|null indexed by slot name
     */
    private ?array $slotCache = null;

    /**
     * @param string        $key
     * @param CmsSlotEntity $entity
     */
    public function set($key, $entity): void
    {
        parent::set($key, $entity);

        $this->slotCache[$entity->getSlot()] = $entity;
    }

    /**
     * @param CmsSlotEntity $entity
     */
    public function add($entity): void
    {
        parent::add($entity);

        $this->slotCache[$entity->getSlot()] = $entity;
    }

    public function getSlot(string $slot): ?CmsSlotEntity
    {
        $this->createSlotHashMap();

        return $this->slotCache[$slot] ?? null;
    }

    public function getApiAlias(): string
    {
        return 'cms_page_slot_collection';
    }

    protected function getExpectedClass(): string
    {
        return CmsSlotEntity::class;
    }

    private function createSlotHashMap(): void
    {
        if ($this->slotCache !== null) {
            return;
        }

        foreach ($this->getIterator() as $element) {
            $this->slotCache[$element->getSlot()] = $element;
        }
    }
}
