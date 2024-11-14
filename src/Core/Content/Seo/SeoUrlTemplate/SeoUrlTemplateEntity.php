<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlTemplate;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[Package('buyers-experience')]
class SeoUrlTemplateEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $salesChannelId;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $entityName;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $routeName;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $template;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $isValid;

    /**
     * @var SalesChannelEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $salesChannel;

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(?string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function setEntityName(string $entityName): void
    {
        $this->entityName = $entityName;
    }

    public function getRouteName(): string
    {
        return $this->routeName;
    }

    public function setRouteName(string $routeName): void
    {
        $this->routeName = $routeName;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function setTemplate(string $template): void
    {
        $this->template = $template;
    }

    public function getIsValid(): bool
    {
        return $this->isValid;
    }

    public function setIsValid(bool $isValid): void
    {
        $this->isValid = $isValid;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }
}
