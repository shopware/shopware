<?php
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

namespace Shopware\Search\FacetResult;

use Shopware\Search\FacetResultInterface;

class ArrayFacetResult implements FacetResultInterface
{
    /**
     * @var string
     */
    protected $facetName;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var string[]|float[]|int[]
     */
    protected $values;

    public function __construct(string $facetName, bool $active, array $values)
    {
        $this->facetName = $facetName;
        $this->active = $active;
        $this->values = $values;
    }

    public function getFacetName(): string
    {
        return $this->facetName;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getValues(): array
    {
        return $this->values;
    }
}
