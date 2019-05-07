<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Aggregate\CmsSlotTranslation;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\SlotDataResolver\FieldConfig;
use Shopware\Core\Content\Cms\SlotDataResolver\FieldConfigCollection;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class CmsSlotTranslationEntity extends TranslationEntity
{
    /**
     * @var array|null
     */
    protected $config;

    /**
     * @var FieldConfigCollection|null
     */
    protected $fieldConfig;

    /**
     * @var string
     */
    protected $cmsSlotId;

    /**
     * @var CmsSlotEntity
     */
    protected $cmsSlot;

    /**
     * @var array|null
     */
    protected $customFields;

    public function getConfig(): ?array
    {
        return $this->config;
    }

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function getCmsSlotId(): string
    {
        return $this->cmsSlotId;
    }

    public function setCmsSlotId(string $cmsSlotId): void
    {
        $this->cmsSlotId = $cmsSlotId;
    }

    public function getCmsSlot(): CmsSlotEntity
    {
        return $this->cmsSlot;
    }

    public function setCmsSlot(CmsSlotEntity $cmsSlot): void
    {
        $this->cmsSlot = $cmsSlot;
    }

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): void
    {
        $this->customFields = $customFields;
    }

    public function getFieldConfig(): ?FieldConfigCollection
    {
        if ($this->fieldConfig) {
            return $this->fieldConfig;
        }

        $collection = new FieldConfigCollection();
        $config = $this->config ?? [];

        foreach ($config as $key => $value) {
            $collection->add(
                new FieldConfig($key, $value['source'], $value['value'])
            );
        }

        return $this->fieldConfig = $collection;
    }

    public function setFieldConfig(FieldConfigCollection $fieldConfig): void
    {
        $this->fieldConfig = $fieldConfig;
    }
}
