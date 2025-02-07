<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Aggregate\CmsSlotTranslation;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class CmsSlotTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    /**
     * @var array<mixed>|null
     */
    protected ?array $config = null;

    protected string $cmsSlotId;

    protected ?CmsSlotEntity $cmsSlot = null;

    /**
     * @return array<mixed>|null
     */
    public function getConfig(): ?array
    {
        return $this->config;
    }

    /**
     * @param array<mixed> $config
     */
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

    public function getCmsSlot(): ?CmsSlotEntity
    {
        return $this->cmsSlot;
    }

    public function setCmsSlot(CmsSlotEntity $cmsSlot): void
    {
        $this->cmsSlot = $cmsSlot;
    }
}
