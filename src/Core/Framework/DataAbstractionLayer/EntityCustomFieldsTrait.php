<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

trait EntityCustomFieldsTrait
{
    /**
     * @var array|null
     */
    protected $customFields;

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): void
    {
        $this->customFields = $customFields;
    }
}
