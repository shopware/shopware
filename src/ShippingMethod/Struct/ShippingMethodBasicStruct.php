<?php declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\ShippingMethod\Struct;

use Shopware\Framework\Struct\Struct;

class ShippingMethodBasicStruct extends Struct
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $uuid;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var int
     */
    protected $type;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var string
     */
    protected $comment;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var int
     */
    protected $position;

    /**
     * @var int
     */
    protected $calculation;

    /**
     * @var int
     */
    protected $surchargeCalculation;

    /**
     * @var int
     */
    protected $taxCalculation;

    /**
     * @var float|null
     */
    protected $shippingFree;

    /**
     * @var int|null
     */
    protected $shopId;

    /**
     * @var string|null
     */
    protected $shopUuid;

    /**
     * @var int|null
     */
    protected $customerGroupId;

    /**
     * @var string|null
     */
    protected $customerGroupUuid;

    /**
     * @var int
     */
    protected $bindShippingfree;

    /**
     * @var int|null
     */
    protected $bindTimeFrom;

    /**
     * @var int|null
     */
    protected $bindTimeTo;

    /**
     * @var int|null
     */
    protected $bindInstock;

    /**
     * @var int
     */
    protected $bindLaststock;

    /**
     * @var int|null
     */
    protected $bindWeekdayFrom;

    /**
     * @var int|null
     */
    protected $bindWeekdayTo;

    /**
     * @var float|null
     */
    protected $bindWeightFrom;

    /**
     * @var float|null
     */
    protected $bindWeightTo;

    /**
     * @var float|null
     */
    protected $bindPriceFrom;

    /**
     * @var float|null
     */
    protected $bindPriceTo;

    /**
     * @var string|null
     */
    protected $bindSql;

    /**
     * @var string|null
     */
    protected $statusLink;

    /**
     * @var string|null
     */
    protected $calculationSql;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function setType(int $type): void
    {
        $this->type = $type;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function setComment(string $comment): void
    {
        $this->comment = $comment;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getCalculation(): int
    {
        return $this->calculation;
    }

    public function setCalculation(int $calculation): void
    {
        $this->calculation = $calculation;
    }

    public function getSurchargeCalculation(): int
    {
        return $this->surchargeCalculation;
    }

    public function setSurchargeCalculation(int $surchargeCalculation): void
    {
        $this->surchargeCalculation = $surchargeCalculation;
    }

    public function getTaxCalculation(): int
    {
        return $this->taxCalculation;
    }

    public function setTaxCalculation(int $taxCalculation): void
    {
        $this->taxCalculation = $taxCalculation;
    }

    public function getShippingFree(): ?float
    {
        return $this->shippingFree;
    }

    public function setShippingFree(?float $shippingFree): void
    {
        $this->shippingFree = $shippingFree;
    }

    public function getShopId(): ?int
    {
        return $this->shopId;
    }

    public function setShopId(?int $shopId): void
    {
        $this->shopId = $shopId;
    }

    public function getShopUuid(): ?string
    {
        return $this->shopUuid;
    }

    public function setShopUuid(?string $shopUuid): void
    {
        $this->shopUuid = $shopUuid;
    }

    public function getCustomerGroupId(): ?int
    {
        return $this->customerGroupId;
    }

    public function setCustomerGroupId(?int $customerGroupId): void
    {
        $this->customerGroupId = $customerGroupId;
    }

    public function getCustomerGroupUuid(): ?string
    {
        return $this->customerGroupUuid;
    }

    public function setCustomerGroupUuid(?string $customerGroupUuid): void
    {
        $this->customerGroupUuid = $customerGroupUuid;
    }

    public function getBindShippingfree(): int
    {
        return $this->bindShippingfree;
    }

    public function setBindShippingfree(int $bindShippingfree): void
    {
        $this->bindShippingfree = $bindShippingfree;
    }

    public function getBindTimeFrom(): ?int
    {
        return $this->bindTimeFrom;
    }

    public function setBindTimeFrom(?int $bindTimeFrom): void
    {
        $this->bindTimeFrom = $bindTimeFrom;
    }

    public function getBindTimeTo(): ?int
    {
        return $this->bindTimeTo;
    }

    public function setBindTimeTo(?int $bindTimeTo): void
    {
        $this->bindTimeTo = $bindTimeTo;
    }

    public function getBindInstock(): ?int
    {
        return $this->bindInstock;
    }

    public function setBindInstock(?int $bindInstock): void
    {
        $this->bindInstock = $bindInstock;
    }

    public function getBindLaststock(): int
    {
        return $this->bindLaststock;
    }

    public function setBindLaststock(int $bindLaststock): void
    {
        $this->bindLaststock = $bindLaststock;
    }

    public function getBindWeekdayFrom(): ?int
    {
        return $this->bindWeekdayFrom;
    }

    public function setBindWeekdayFrom(?int $bindWeekdayFrom): void
    {
        $this->bindWeekdayFrom = $bindWeekdayFrom;
    }

    public function getBindWeekdayTo(): ?int
    {
        return $this->bindWeekdayTo;
    }

    public function setBindWeekdayTo(?int $bindWeekdayTo): void
    {
        $this->bindWeekdayTo = $bindWeekdayTo;
    }

    public function getBindWeightFrom(): ?float
    {
        return $this->bindWeightFrom;
    }

    public function setBindWeightFrom(?float $bindWeightFrom): void
    {
        $this->bindWeightFrom = $bindWeightFrom;
    }

    public function getBindWeightTo(): ?float
    {
        return $this->bindWeightTo;
    }

    public function setBindWeightTo(?float $bindWeightTo): void
    {
        $this->bindWeightTo = $bindWeightTo;
    }

    public function getBindPriceFrom(): ?float
    {
        return $this->bindPriceFrom;
    }

    public function setBindPriceFrom(?float $bindPriceFrom): void
    {
        $this->bindPriceFrom = $bindPriceFrom;
    }

    public function getBindPriceTo(): ?float
    {
        return $this->bindPriceTo;
    }

    public function setBindPriceTo(?float $bindPriceTo): void
    {
        $this->bindPriceTo = $bindPriceTo;
    }

    public function getBindSql(): ?string
    {
        return $this->bindSql;
    }

    public function setBindSql(?string $bindSql): void
    {
        $this->bindSql = $bindSql;
    }

    public function getStatusLink(): ?string
    {
        return $this->statusLink;
    }

    public function setStatusLink(?string $statusLink): void
    {
        $this->statusLink = $statusLink;
    }

    public function getCalculationSql(): ?string
    {
        return $this->calculationSql;
    }

    public function setCalculationSql(?string $calculationSql): void
    {
        $this->calculationSql = $calculationSql;
    }
}
