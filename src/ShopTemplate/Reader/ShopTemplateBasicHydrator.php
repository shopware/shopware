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

namespace Shopware\ShopTemplate\Reader;

use Shopware\Framework\Struct\Hydrator;
use Shopware\ShopTemplate\Struct\ShopTemplateBasicStruct;

class ShopTemplateBasicHydrator extends Hydrator
{
    public function __construct()
    {
    }

    public function hydrate(array $data): ShopTemplateBasicStruct
    {
        $shopTemplate = new ShopTemplateBasicStruct();

        $shopTemplate->setId((int)$data['__shopTemplate_id']);
        $shopTemplate->setUuid((string)$data['__shopTemplate_uuid']);
        $shopTemplate->setTemplate((string)$data['__shopTemplate_template']);
        $shopTemplate->setName((string)$data['__shopTemplate_name']);
        $shopTemplate->setDescription(
            isset($data['__shopTemplate_description']) ? (string)$data['__shopTemplate_description'] : null
        );
        $shopTemplate->setAuthor(isset($data['__shopTemplate_author']) ? (string)$data['__shopTemplate_author'] : null);
        $shopTemplate->setLicense(
            isset($data['__shopTemplate_license']) ? (string)$data['__shopTemplate_license'] : null
        );
        $shopTemplate->setEsi((bool)$data['__shopTemplate_esi']);
        $shopTemplate->setStyleSupport((bool)$data['__shopTemplate_style_support']);
        $shopTemplate->setEmotion((bool)$data['__shopTemplate_emotion']);
        $shopTemplate->setVersion((int)$data['__shopTemplate_version']);
        $shopTemplate->setPluginId(
            isset($data['__shopTemplate_plugin_id']) ? (int)$data['__shopTemplate_plugin_id'] : null
        );
        $shopTemplate->setPluginUuid(
            isset($data['__shopTemplate_plugin_uuid']) ? (string)$data['__shopTemplate_plugin_uuid'] : null
        );
        $shopTemplate->setParentId(
            isset($data['__shopTemplate_parent_id']) ? (int)$data['__shopTemplate_parent_id'] : null
        );
        $shopTemplate->setParentUuid(
            isset($data['__shopTemplate_parent_uuid']) ? (string)$data['__shopTemplate_parent_uuid'] : null
        );

        return $shopTemplate;
    }
}
