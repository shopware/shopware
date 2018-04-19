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

use Shopware\CountryArea\Struct\CountryAreaHydrator;
use Shopware\Framework\Struct\AttributeHydrator;
use Shopware\Framework\Struct\Hydrator;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class CountryHydrator extends Hydrator
{
    /**
     * @var AttributeHydrator
     */
    private $attributeHydrator;

    /**
     * @var CountryAreaHydrator
     */
    private $countryAreaHydrator;

    public function __construct(AttributeHydrator $attributeHydrator, CountryAreaHydrator $countryAreaHydrator)
    {
        $this->attributeHydrator = $attributeHydrator;
        $this->countryAreaHydrator = $countryAreaHydrator;
    }

    public function hydrateIdentity(array $data): CountryIdentity
    {
        $identity = new CountryIdentity();

        $this->assign($data, $identity);

        return $identity;
    }

    public function hydrate(array $data): Country
    {
        $country = new Country();

        $this->assign($data, $country);

        if ($data['__countryArea_id']) {
            $country->setArea($this->countryAreaHydrator->hydrate($data));
        }
        return $country;
    }

    private function assign(array $data, CountryIdentity $identity)
    {
        $id = (int) $data['__country_id'];

        $translation = $this->getTranslation($data, '__country', [], $id);
        $data = array_merge($data, $translation);

        $identity->assign([
            'id' => (int) $data['__country_id'],
            'countryName' => $data['__country_countryname'],
            'countryIso' => $data['__country_countryiso'],
            'areaId' => $data['__country_areaID'],
            'countryEn' => $data['__country_countryen'],
            'position' => $data['__country_position'],
            'notice' => $data['__country_notice'],
            'shippingFree' => $data['__country_shippingfree'],
            'taxFree' => $data['__country_taxfree'],
            'taxFreeUstid' => $data['__country_taxfree_ustid'],
            'taxFreeUstidChecked' => $data['__country_taxfree_ustid_checked'],
            'active' => $data['__country_active'],
            'iso3' => $data['__country_iso3'],
            'displayStateInRegistration' => $data['__country_display_state_in_registration'],
            'forceStateInRegistration' => $data['__country_force_state_in_registration'],
        ]);

        if ($data['__countryAttribute_id'] !== null) {
            $this->attributeHydrator->addAttribute($identity, $data, 'countryAttribute');
        }
    }
}
