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

namespace Shopware\ShopTemplate\Struct;

use Shopware\Framework\Struct\Hydrator;

class ShopTemplateHydrator extends Hydrator
{
    public function hydrate($data): ShopTemplate
    {
        $template = new ShopTemplate();
        $template->setId((int) $data['__template_id']);
        $template->setTemplate($data['__template_template']);
        $template->setName($data['__template_name']);
        $template->setDescription($data['__template_description']);
        $template->setAuthor($data['__template_author']);
        $template->setLicense($data['__template_license']);
        $template->setVersion((int) $data['__template_version']);
        $template->setPluginId((int) $data['__template_plugin_id']);
        $template->setParentId((int) $data['__template_parent_id']);

        return $template;
    }
}
