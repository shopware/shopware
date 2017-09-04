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

namespace Shopware\AreaCountryState\Struct;

use Shopware\Framework\Struct\Struct;

class AreaCountryStateBasicStruct extends Struct
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
    protected $areaCountryId;

    /**
     * @var string|null
     */
    protected $areaCountryUuid;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var string
     */
    protected $shortCode;

    /**
     * @var int|null
     */
    protected $position;

    /**
     * @var bool|null
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getShortCode(): string
    {
        return $this->shortCode;
    }

    public function setShortCode(string $shortCode): void
    {
        $this->shortCode = $shortCode;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
    {
        $this->position = $position;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(?bool $active): void
    {
        $this->active = $active;
    }
}
