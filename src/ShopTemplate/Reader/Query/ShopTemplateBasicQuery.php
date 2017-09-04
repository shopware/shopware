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

namespace Shopware\ShopTemplate\Reader\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;

class ShopTemplateBasicQuery extends QueryBuilder
{
    public function __construct(Connection $connection, TranslationContext $context)
    {
        parent::__construct($connection);

        $this->from('shop_template', 'shopTemplate');

        self::addRequirements($this, $context);
    }

    public static function addRequirements(QueryBuilder $query, TranslationContext $context)
    {
        $query->addSelect([
            'shopTemplate.uuid as _array_key_',
            'shopTemplate.id as __shopTemplate_id',
            'shopTemplate.uuid as __shopTemplate_uuid',
            'shopTemplate.template as __shopTemplate_template',
            'shopTemplate.name as __shopTemplate_name',
            'shopTemplate.description as __shopTemplate_description',
            'shopTemplate.author as __shopTemplate_author',
            'shopTemplate.license as __shopTemplate_license',
            'shopTemplate.esi as __shopTemplate_esi',
            'shopTemplate.style_support as __shopTemplate_style_support',
            'shopTemplate.emotion as __shopTemplate_emotion',
            'shopTemplate.version as __shopTemplate_version',
            'shopTemplate.plugin_id as __shopTemplate_plugin_id',
            'shopTemplate.plugin_uuid as __shopTemplate_plugin_uuid',
            'shopTemplate.parent_id as __shopTemplate_parent_id',
            'shopTemplate.parent_uuid as __shopTemplate_parent_uuid',
        ]);

        //$query->leftJoin('shopTemplate', 'shopTemplate_translation', 'shopTemplateTranslation', 'shopTemplate.uuid = shopTemplateTranslation.shopTemplate_uuid AND shopTemplateTranslation.language_uuid = :languageUuid');
        //$query->setParameter('languageUuid', $context->getShopUuid());
    }
}
