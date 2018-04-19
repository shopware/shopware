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

namespace Shopware\Country\Struct;

use Shopware\CountryArea\Struct\CountryArea;
use Shopware\CountryState\Struct\CountryStateCollection;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class Country extends CountryIdentity
{
    /**
     * @var CountryArea
     */
    protected $area;

    /**
     * @var CountryStateCollection
     */
    protected $states;

    public function getArea(): CountryArea
    {
        return $this->area;
    }

    public function setArea(CountryArea $area): void
    {
        $this->area = $area;
    }

    public function getStates(): CountryStateCollection
    {
        return $this->states;
    }

    public function setStates(CountryStateCollection $states): void
    {
        $this->states = $states;
    }
}
