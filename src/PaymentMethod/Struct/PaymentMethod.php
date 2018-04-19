<?php
declare(strict_types=1);
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

use Shopware\Cart\Rule\Rule;
use Shopware\Cart\Rule\Validatable;
use Shopware\Framework\Struct\Struct;

class PaymentMethod extends Struct implements Validatable
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var string
     */
    protected $template;

    /**
     * @var string
     */
    protected $description;

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
    protected $hidden;

    /**
     * @var float|null
     */
    protected $percentageSurcharge;

    /**
     * @var float|null
     */
    protected $surcharge;

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
    protected $esdActive;

    /**
     * @var string|null
     */
    protected $iFrameUrl;

    /**
     * @var string|null
     */
    protected $action;

    /**
     * @var int|null
     */
    protected $pluginId;

    /**
     * @var int|null
     */
    protected $source;

    /**
     * @var bool
     */
    protected $mobileInactive;

    /**
     * @var Rule|null
     */
    protected $rule;

    public function __construct(int $id, string $name, string $label, string $class)
    {
        $this->id = $id;
        $this->name = $name;
        $this->label = $label;
        $this->class = $class;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function setTemplate(string $template): void
    {
        $this->template = $template;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
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

    /**
     * @return bool
     */
    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): void
    {
        $this->hidden = $hidden;
    }

    public function getPercentageSurcharge(): ?float
    {
        return $this->percentageSurcharge;
    }

    public function setPercentageSurcharge(?float $percentageSurcharge): void
    {
        $this->percentageSurcharge = $percentageSurcharge;
    }

    public function getSurcharge(): ?float
    {
        return $this->surcharge;
    }

    public function setSurcharge(?float $surcharge): void
    {
        $this->surcharge = $surcharge;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function isEsdActive(): bool
    {
        return $this->esdActive;
    }

    public function setEsdActive(bool $esdActive): void
    {
        $this->esdActive = $esdActive;
    }

    public function getIFrameUrl(): ? string
    {
        return $this->iFrameUrl;
    }

    public function setIFrameUrl(?string $iFrameUrl): void
    {
        $this->iFrameUrl = $iFrameUrl;
    }

    public function getAction(): ? string
    {
        return $this->action;
    }

    public function setAction(?string $action): void
    {
        $this->action = $action;
    }

    public function getPluginId(): ? int
    {
        return $this->pluginId;
    }

    public function setPluginId(?int $pluginId): void
    {
        $this->pluginId = $pluginId;
    }

    public function getSource(): ? int
    {
        return $this->source;
    }

    public function setSource(?int $source): void
    {
        $this->source = $source;
    }

    public function isMobileInactive(): bool
    {
        return $this->mobileInactive;
    }

    public function setMobileInactive(bool $mobileInactive): void
    {
        $this->mobileInactive = $mobileInactive;
    }

    public function getRule(): ?Rule
    {
        return $this->rule;
    }

    public function setRule(?Rule $rule): void
    {
        $this->rule = $rule;
    }
}
