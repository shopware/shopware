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

namespace Shopware\ProductStream\Struct;

use Shopware\Framework\Struct\AttributeHydrator;
use Shopware\Framework\Struct\Hydrator;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class ProductStreamHydrator extends Hydrator
{
    /**
     * @var AttributeHydrator
     */
    private $attributeHydrator;

    public function __construct(AttributeHydrator $attributeHydrator)
    {
        $this->attributeHydrator = $attributeHydrator;
    }

    public function hydrate(array $data): ProductStream
    {
        $productStream = new ProductStream();
        $translation = $this->getTranslation($data, '__stream');
        $data = array_merge($data, $translation);

        if (isset($data['__stream_id'])) {
            $productStream->setId((int) $data['__stream_id']);
        }

        if (isset($data['__stream_name'])) {
            $productStream->setName($data['__stream_name']);
        }

        if (isset($data['__stream_description'])) {
            $productStream->setDescription($data['__stream_description']);
        }

        if (isset($data['__stream_type'])) {
            $productStream->setType((int) $data['__stream_type']);
        }
        if ($data['__productStreamAttribute_id']) {
            $this->attributeHydrator->addAttribute($productStream, $data, 'productStreamAttribute', null, 'stream');
        }

        return $productStream;
    }
}
