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

namespace Shopware\Tests\Unit\Components\Model;

use Shopware\Components\Model\ModelManager;

class ModelManagerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var ModelManager
     */
    private $modelManager;

    public function setUp()
    {
        $this->modelManager = $this->createPartialMock(ModelManager::class, []);
    }

    /**
     * Call protected/private method of a class.
     *
     * @param object $object     instantiated object that we will run method on
     * @param string $methodName Method name to call
     * @param array  $parameters array of parameters to pass into method
     *
     * @return mixed method return
     */
    public function invokeMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * @return array
     */
    public function getSqlTypes()
    {
        return [
            // integer
            ['INT', 'integer'],
            ['integer', 'integer'],
            ['int(11)', 'integer'],
            ['tinyint(1)', 'integer'],
            ['smallint(1)', 'integer'],
            ['mediumint(1)', 'integer'],
            ['bigint(20)', 'integer'],

            // boolean
            ['boolean', 'boolean'],
            ['bool', 'boolean'],

            // float
            ['decimal', 'float'],
            ['dec', 'float'],
            ['numeric', 'float'],
            ['fixed', 'float'],
            ['double(2,2)', 'float'],
            ['double', 'float'],
            ['float', 'float'],
            ['float(4,6)', 'float'],

            // string
            ['varchar(255)', 'string'],
            ['varchar', 'string'],
            ['char(2)', 'string'],
            ['char', 'string'],

            // date / datetime
            ['date', 'date'],
            ['datetimetz', 'date'],
            ['datetime', 'datetime'],
            ['timestamp', 'datetime'],

            // text
            ['text', 'text'],
            ['blob', 'text'],
            ['binary', 'text'],
            ['simple_array', 'text'],
            ['json_array', 'text'],
            ['object', 'text'],
            ['guid', 'text'],
        ];
    }

    /**
     * @dataProvider getSqlTypes
     *
     * @param string $sqlType
     * @param string $expectedAttributeType
     */
    public function testConvertSqlTypeToAttributeType($sqlType, $expectedAttributeType)
    {
        $convertedSqlType = $this->invokeMethod($this->modelManager, 'convertColumnType', [$sqlType]);
        $this->assertEquals($expectedAttributeType, $convertedSqlType);
    }
}
