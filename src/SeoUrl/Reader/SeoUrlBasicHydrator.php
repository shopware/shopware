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

namespace Shopware\SeoUrl\Reader;

use Shopware\Framework\Struct\Hydrator;
use Shopware\SeoUrl\Struct\SeoUrlBasicStruct;

class SeoUrlBasicHydrator extends Hydrator
{
    public function __construct()
    {
    }

    public function hydrate(array $data): SeoUrlBasicStruct
    {
        $seoUrl = new SeoUrlBasicStruct();

        $seoUrl->setId((int) $data['__seoUrl_id']);
        $seoUrl->setUuid((string) $data['__seoUrl_uuid']);
        $seoUrl->setSeoHash((string) $data['__seoUrl_seo_hash']);
        $seoUrl->setShopUuid((string) $data['__seoUrl_shop_uuid']);
        $seoUrl->setName((string) $data['__seoUrl_name']);
        $seoUrl->setForeignKey((string) $data['__seoUrl_foreign_key']);
        $seoUrl->setPathInfo((string) $data['__seoUrl_path_info']);
        $seoUrl->setSeoPathInfo((string) $data['__seoUrl_seo_path_info']);
        $seoUrl->setIsCanonical((bool) $data['__seoUrl_is_canonical']);
        $seoUrl->setCreatedAt(new \DateTime($data['__seoUrl_created_at']));

        return $seoUrl;
    }
}
