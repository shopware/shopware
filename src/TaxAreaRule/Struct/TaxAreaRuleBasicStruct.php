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

namespace Shopware\TaxAreaRule\Struct;

use Shopware\Framework\Struct\Struct;

class TaxAreaRuleBasicStruct extends Struct
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
     * @var int|null
     */
    protected $areaId;

    /**
     * @var string|null
     */
    protected $areaUuid;

    /**
     * @var int|null
     */
    protected $areaCountryId;

    /**
     * @var string|null
     */
    protected $areaCountryUuid;

    /**
     * @var int|null
     */
    protected $areaCountryStateId;

    /**
     * @var string|null
     */
    protected $areaCountryStateUuid;

    /**
     * @var int
     */
    protected $taxId;

    /**
     * @var string
     */
    protected $taxUuid;

    /**
     * @var int
     */
    protected $customerGroupId;

    /**
     * @var string
     */
    protected $customerGroupUuid;

    /**
     * @var float
     */
    protected $taxRate;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var bool
     */
    protected $active;

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

    public function getAreaId(): ?int
    {
        return $this->areaId;
    }

    public function setAreaId(?int $areaId): void
    {
        $this->areaId = $areaId;
    }

    public function getAreaUuid(): ?string
    {
        return $this->areaUuid;
    }

    public function setAreaUuid(?string $areaUuid): void
    {
        $this->areaUuid = $areaUuid;
    }

    public function getAreaCountryId(): ?int
    {
        return $this->areaCountryId;
    }

    public function setAreaCountryId(?int $areaCountryId): void
    {
        $this->areaCountryId = $areaCountryId;
    }

    public function getAreaCountryUuid(): ?string
    {
        return $this->areaCountryUuid;
    }

    public function setAreaCountryUuid(?string $areaCountryUuid): void
    {
        $this->areaCountryUuid = $areaCountryUuid;
    }

    public function getAreaCountryStateId(): ?int
    {
        return $this->areaCountryStateId;
    }

    public function setAreaCountryStateId(?int $areaCountryStateId): void
    {
        $this->areaCountryStateId = $areaCountryStateId;
    }

    public function getAreaCountryStateUuid(): ?string
    {
        return $this->areaCountryStateUuid;
    }

    public function setAreaCountryStateUuid(?string $areaCountryStateUuid): void
    {
        $this->areaCountryStateUuid = $areaCountryStateUuid;
    }

    public function getTaxId(): int
    {
        return $this->taxId;
    }

    public function setTaxId(int $taxId): void
    {
        $this->taxId = $taxId;
    }

    public function getTaxUuid(): string
    {
        return $this->taxUuid;
    }

    public function setTaxUuid(string $taxUuid): void
    {
        $this->taxUuid = $taxUuid;
    }

    public function getCustomerGroupId(): int
    {
        return $this->customerGroupId;
    }

    public function setCustomerGroupId(int $customerGroupId): void
    {
        $this->customerGroupId = $customerGroupId;
    }

    public function getCustomerGroupUuid(): string
    {
        return $this->customerGroupUuid;
    }

    public function setCustomerGroupUuid(string $customerGroupUuid): void
    {
        $this->customerGroupUuid = $customerGroupUuid;
    }

    public function getTaxRate(): float
    {
        return $this->taxRate;
    }

    public function setTaxRate(float $taxRate): void
    {
        $this->taxRate = $taxRate;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }
}
