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

namespace Shopware\PaymentMethod\Struct;

use Shopware\Framework\Struct\Struct;

class PaymentMethodBasicStruct extends Struct
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
     * @var string
     */
    protected $description;

    /**
     * @var string
     */
    protected $template;

    /**
     * @var string
     */
    protected $class;

    /**
     * @var string
     */
    protected $table;

    /**
     * @var bool
     */
    protected $hide;

    /**
     * @var string
     */
    protected $additionalDescription;

    /**
     * @var float
     */
    protected $debitPercent;

    /**
     * @var float
     */
    protected $surcharge;

    /**
     * @var string
     */
    protected $surchargeString;

    /**
     * @var int
     */
    protected $position;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var bool
     */
    protected $allowEsd;

    /**
     * @var string
     */
    protected $usedIframe;

    /**
     * @var bool
     */
    protected $hideProspect;

    /**
     * @var string|null
     */
    protected $action;

    /**
     * @var int|null
     */
    protected $pluginId;

    /**
     * @var string|null
     */
    protected $pluginUuid;

    /**
     * @var int|null
     */
    protected $source;

    /**
     * @var bool
     */
    protected $mobileInactive;

    /**
     * @var string|null
     */
    protected $riskRules;

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

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function setTemplate(string $template): void
    {
        $this->template = $template;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function setClass(string $class): void
    {
        $this->class = $class;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function setTable(string $table): void
    {
        $this->table = $table;
    }

    public function getHide(): bool
    {
        return $this->hide;
    }

    public function setHide(bool $hide): void
    {
        $this->hide = $hide;
    }

    public function getAdditionalDescription(): string
    {
        return $this->additionalDescription;
    }

    public function setAdditionalDescription(string $additionalDescription): void
    {
        $this->additionalDescription = $additionalDescription;
    }

    public function getDebitPercent(): float
    {
        return $this->debitPercent;
    }

    public function setDebitPercent(float $debitPercent): void
    {
        $this->debitPercent = $debitPercent;
    }

    public function getSurcharge(): float
    {
        return $this->surcharge;
    }

    public function setSurcharge(float $surcharge): void
    {
        $this->surcharge = $surcharge;
    }

    public function getSurchargeString(): string
    {
        return $this->surchargeString;
    }

    public function setSurchargeString(string $surchargeString): void
    {
        $this->surchargeString = $surchargeString;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getAllowEsd(): bool
    {
        return $this->allowEsd;
    }

    public function setAllowEsd(bool $allowEsd): void
    {
        $this->allowEsd = $allowEsd;
    }

    public function getUsedIframe(): string
    {
        return $this->usedIframe;
    }

    public function setUsedIframe(string $usedIframe): void
    {
        $this->usedIframe = $usedIframe;
    }

    public function getHideProspect(): bool
    {
        return $this->hideProspect;
    }

    public function setHideProspect(bool $hideProspect): void
    {
        $this->hideProspect = $hideProspect;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(?string $action): void
    {
        $this->action = $action;
    }

    public function getPluginId(): ?int
    {
        return $this->pluginId;
    }

    public function setPluginId(?int $pluginId): void
    {
        $this->pluginId = $pluginId;
    }

    public function getPluginUuid(): ?string
    {
        return $this->pluginUuid;
    }

    public function setPluginUuid(?string $pluginUuid): void
    {
        $this->pluginUuid = $pluginUuid;
    }

    public function getSource(): ?int
    {
        return $this->source;
    }

    public function setSource(?int $source): void
    {
        $this->source = $source;
    }

    public function getMobileInactive(): bool
    {
        return $this->mobileInactive;
    }

    public function setMobileInactive(bool $mobileInactive): void
    {
        $this->mobileInactive = $mobileInactive;
    }

    public function getRiskRules(): ?string
    {
        return $this->riskRules;
    }

    public function setRiskRules(?string $riskRules): void
    {
        $this->riskRules = $riskRules;
    }
}
