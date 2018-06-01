<?php declare(strict_types=1);
/**
 * Shopware\Core 5
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
 * "Shopware\Core" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Core\Framework\ORM\Write\FieldAware;

use Shopware\Core\Framework\ORM\Field\Field;

class FieldExtenderCollection extends FieldExtender
{
    /**
     * @var FieldExtender[]
     */
    private $fieldExtenders = [];

    public function addExtender(FieldExtender $extender): void
    {
        $this->fieldExtenders[] = $extender;
    }

    public function extend(Field $field): void
    {
        foreach ($this->fieldExtenders as $fieldExtender) {
            $fieldExtender->extend($field);
        }

        $field->setFieldExtenderCollection($this);
    }
}
