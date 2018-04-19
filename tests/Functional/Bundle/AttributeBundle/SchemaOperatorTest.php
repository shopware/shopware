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

namespace Shopware\Tests\Functional\Bundle\AttributeBundle;

class SchemaOperatorTest extends \PHPUnit\Framework\TestCase
{
    public function testDefaultValues()
    {
        $types = [
            'string' => 'test123',
            'integer' => 123,
            'float' => 123,
            'boolean' => 1,
            'date' => '2010-01-01',
            'datetime' => '2010-01-01 10:00:00',
            'text' => 'test123',
            'html' => 'test123',
            'combobox' => '1',
            'multi_selection' => '1',
            'single_selection' => 'SW10003',
        ];

        $this->iterateTypeArray($types);
    }

    public function testNullDefaultValues()
    {
        $types = [
            'string' => null,
            'integer' => null,
            'float' => null,
            'boolean' => null,
            'date' => null,
            'datetime' => null,
            'text' => null,
            'html' => null,
            'combobox' => null,
            'multi_selection' => null,
            'single_selection' => null,
        ];

        $this->iterateTypeArray($types);
    }

    public function testNullStringDefaultValues()
    {
        $types = [
            'string' => 'NULL',
            'integer' => 'NULL',
            'float' => 'NULL',
            'boolean' => 'NULL',
            'date' => 'NULL',
            'datetime' => 'NULL',
            'text' => 'NULL',
            'html' => 'NULL',
            'combobox' => 'NULL',
            'multi_selection' => 'NULL',
            'single_selection' => 'NULL',
        ];

        $this->iterateTypeArray($types);
    }

    /**
     * @param $types
     *
     * @throws \Exception
     */
    private function iterateTypeArray($types)
    {
        $service = Shopware()->Container()->get('shopware_attribute.crud_service');
        $tableMapping = Shopware()->Container()->get('shopware_attribute.table_mapping');
        $table = 's_articles_attributes';

        foreach ($types as $type => $default) {
            $name = 'attr_' . $type;

            if ($tableMapping->isTableColumn($table, $name)) {
                $service->delete($table, $name);
            }

            $service->update('s_articles_attributes', $name, $type, [], null, false, $default);

            $this->assertTrue($tableMapping->isTableColumn($table, $name));
            $service->delete($table, $name);
        }
    }
}
