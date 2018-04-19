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
class Shopware_Tests_Components_Snippet_SnippetStructureTest extends Enlight_Components_Test_TestCase
{
    /**
     * Test case
     */
    public function testSnippetsShouldBeValid()
    {
        $source = Shopware()->Container()->getParameter('kernel.root_dir') . '/snippets';

        $validator = Shopware()->Container()->get('shopware.snippet_validator');

        $validationResult = $validator->validate($source);

        $pluginBasePath = Shopware()->Container()->get('application')->AppPath('Plugins_Default');
        foreach (['Backend', 'Core', 'Frontend'] as $namespace) {
            foreach (new \DirectoryIterator($pluginBasePath . $namespace) as $pluginDir) {
                if ($pluginDir->isDot() || !$pluginDir->isDir()) {
                    continue;
                }

                $validationResult = array_merge($validationResult, $validator->validate($pluginDir->getPathname()));
            }
        }

        $this->assertEmpty($validationResult, "Snippet validation errors detected: \n" . implode("\n", $validationResult));
    }
}
