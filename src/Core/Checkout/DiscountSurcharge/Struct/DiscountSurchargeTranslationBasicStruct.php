<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Struct;

use Shopware\Core\Framework\ORM\Entity;

class DiscountSurchargeTranslationBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $discountSurchargeId;

    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var string
     */
    protected $name;

    public function getDiscountSurchargeId(): string
    {
        return $this->discountSurchargeId;
    }

    public function setDiscountSurchargeId(string $discountSurchargeId): void
    {
        $this->discountSurchargeId = $discountSurchargeId;
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
