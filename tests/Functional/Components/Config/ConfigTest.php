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

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.com)
 */
class ConfigTest extends Enlight_Components_Test_TestCase
{
    /**
     * Test case
     */
    public function testMissingSnippetsConfigSection()
    {
        // Configure snippet database adapter
        $adapter = new Shopware\Components\Snippet\DbAdapter([
            'sectionColumn' => ['shopID', 'localeID'],
        ]);

        // Simple default config object with section
        $config = new \Enlight_Config([], [
            'section' => '1:1',
        ]);

        // Write multiple times
        $adapter->write($config);
        $adapter->write($config);
        $adapter->write($config);

        // Section should not be modified
        $this->assertEquals('1:1', $config->getSection());
    }
}
