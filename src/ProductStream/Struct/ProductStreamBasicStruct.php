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

namespace Shopware\ProductStream\Struct;

use Shopware\Framework\Struct\Struct;
use Shopware\ListingSorting\Struct\ListingSortingBasicStruct;

class ProductStreamBasicStruct extends Struct
{
    /**
     * @var string
     */
    protected $uuid;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $conditions;

    /**
     * @var int|null
     */
    protected $type;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var string|null
     */
    protected $listingSortingUuid;

    /**
     * @var ListingSortingBasicStruct
     */
    protected $sorting;

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

    public function getConditions(): ?string
    {
        return $this->conditions;
    }

    public function setConditions(?string $conditions): void
    {
        $this->conditions = $conditions;
    }

    public function getType(): ?int
    {
        return $this->type;
    }

    public function setType(?int $type): void
    {
        $this->type = $type;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getListingSortingUuid(): ?string
    {
        return $this->listingSortingUuid;
    }

    public function setListingSortingUuid(?string $listingSortingUuid): void
    {
        $this->listingSortingUuid = $listingSortingUuid;
    }

    public function getSorting(): ListingSortingBasicStruct
    {
        return $this->sorting;
    }

    public function setSorting(ListingSortingBasicStruct $sorting): void
    {
        $this->sorting = $sorting;
    }
}
