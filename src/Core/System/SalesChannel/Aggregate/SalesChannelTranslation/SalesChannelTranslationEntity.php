<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class SalesChannelTranslationEntity extends TranslationEntity
{
    /**
     * @var string
     */
    protected $salesChannelId;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var SalesChannelEntity|null
     */
    protected $salesChannel;

    /**
     * @var array|null
     */
    protected $customFields;

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): void
    {
        $this->customFields = $customFields;
    }

    public function getApiAlias(): string
    {
        return 'sales_channel_translation';
    }
}
