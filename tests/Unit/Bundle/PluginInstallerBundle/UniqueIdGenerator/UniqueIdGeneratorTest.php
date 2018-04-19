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

use PHPUnit\Framework\TestCase;
use Shopware\Bundle\PluginInstallerBundle\Service\UniqueIdGenerator;

class UniqueIdGeneratorTest extends TestCase
{
    /**
     * Tests if an existing unique id is returned and not stored again.
     */
    public function testReturnUniqueIdFromDb()
    {
        $connectionMock = $this->getMockBuilder(\Doctrine\DBAL\Connection::class)
            ->disableOriginalConstructor()
            ->setMethods(['fetchColumn', 'executeUpdate'])
            ->getMock();

        $connectionMock->expects($this->exactly(1))
            ->method('fetchColumn')
            ->willReturn('s:32:"xErV4zUsI28DVKfayeIB6rqIOBjR8OEB";');

        $connectionMock->expects($this->exactly(0))
            ->method('executeUpdate')
            ->willReturn(true);

        $dbStorage = new UniqueIdGenerator\UniqueIdGenerator(
            $connectionMock
        );

        $this->assertEquals('xErV4zUsI28DVKfayeIB6rqIOBjR8OEB', $dbStorage->getUniqueId());
    }

    /**
     * Tests if all necessary methods are called to check for an old id in the db
     * and generate & store a new one if none exists.
     */
    public function testStoringGeneratedIdInDb()
    {
        $connectionMock = $this->getMockBuilder(\Doctrine\DBAL\Connection::class)
            ->disableOriginalConstructor()
            ->setMethods(['fetchColumn', 'executeUpdate'])
            ->getMock();

        $connectionMock->expects($this->exactly(1))
            ->method('fetchColumn')
            ->willReturn(null);

        $connectionMock->expects($this->exactly(1))
            ->method('executeUpdate')
            ->willReturn(true);

        $dbStorage = new UniqueIdGenerator\UniqueIdGenerator(
            $connectionMock
        );

        $this->assertNotNull($dbStorage->getUniqueId());
    }
}
