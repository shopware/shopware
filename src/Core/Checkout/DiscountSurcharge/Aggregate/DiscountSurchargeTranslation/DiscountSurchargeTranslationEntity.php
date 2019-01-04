<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation;

use Shopware\Core\Checkout\DiscountSurcharge\DiscountSurchargeEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class DiscountSurchargeTranslationEntity extends TranslationEntity
{
    use EntityIdTrait;
    /**
     * @var string
     */
    protected $discountSurchargeId;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var DiscountSurchargeEntity|null
     */
    protected $discountSurcharge;

    public function getDiscountSurchargeId(): string
    {
        return $this->discountSurchargeId;
    }

    public function setDiscountSurchargeId(string $discountSurchargeId): void
    {
        $this->discountSurchargeId = $discountSurchargeId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getDiscountSurcharge(): ?DiscountSurchargeEntity
    {
        return $this->discountSurcharge;
    }

    public function setDiscountSurcharge(DiscountSurchargeEntity $discountSurcharge): void
    {
        $this->discountSurcharge = $discountSurcharge;
    }
}
