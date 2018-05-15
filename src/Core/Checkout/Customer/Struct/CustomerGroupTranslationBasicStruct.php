<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Struct;

use Shopware\Framework\ORM\Entity;

class CustomerGroupTranslationBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $customerGroupId;

    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var string
     */
    protected $name;

    public function getCustomerGroupId(): string
    {
        return $this->customerGroupId;
    }

    public function setCustomerGroupId(string $customerGroupId): void
    {
        $this->customerGroupId = $customerGroupId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
