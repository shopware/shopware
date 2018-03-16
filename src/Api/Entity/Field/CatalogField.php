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

namespace Shopware\Api\Entity\Field;

use Shopware\Framework\Struct\Uuid;
use Shopware\Api\Catalog\Definition\CatalogDefinition;
use Shopware\Api\Entity\Write\DataStack\KeyValuePair;
use Shopware\Api\Entity\Write\EntityExistence;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Defaults;

class CatalogField extends FkField
{
    public function __construct()
    {
        parent::__construct('catalog_id', 'catalogId', CatalogDefinition::class);

        $this->setFlags(new Required());
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(EntityExistence $existence, KeyValuePair $kvPair): \Generator
    {
        if ($this->writeContext->has($this->definition, 'catalogId')) {
            $value = $this->writeContext->get($this->definition, 'catalogId');
        } elseif (!empty($kvPair->getValue())) {
            $value = $kvPair->getValue();
        } else {
            $value = Defaults::CATALOG;
        }

        //write catalog id of current object to write context
        $this->writeContext->set($this->definition, 'catalogId', $value);

        yield $this->storageName => Uuid::fromStringToBytes($value);
    }
}
