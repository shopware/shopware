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

namespace Shopware\CountryState\Struct;

use Shopware\Framework\Struct\AttributeHydrator;
use Shopware\Framework\Struct\Hydrator;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class CountryStateHydrator extends Hydrator
{
    /**
     * @var AttributeHydrator
     */
    private $attributeHydrator;

    /**
     * @param AttributeHydrator $attributeHydrator
     */
    public function __construct(AttributeHydrator $attributeHydrator)
    {
        $this->attributeHydrator = $attributeHydrator;
    }

    public function hydrate(array $data): CountryState
    {
        $state = new CountryState();

        $id = (int) $data['__countryState_id'];

        $translation = $this->getTranslation($data, '__countryState', [], $id);
        $data = array_merge($data, $translation);

        $state->setId($id);

        if (isset($data['__countryState_name'])) {
            $state->setName($data['__countryState_name']);
        }

        if (isset($data['__countryState_shortcode'])) {
            $state->setCode($data['__countryState_shortcode']);
        }
        $state->setPosition((int) $data['__countryState_position']);

        if ($data['__countryStateAttribute_id'] !== null) {
            $this->attributeHydrator->addAttribute($state, $data, 'countryStateAttribute');
        }

        return $state;
    }
}
