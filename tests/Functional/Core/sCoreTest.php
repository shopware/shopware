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

class sCoreTest extends Enlight_Components_Test_Controller_TestCase
{
    /**
     * @var sCore
     */
    private $module;

    public function setUp()
    {
        $this->module = Shopware()->Modules()->Core();
    }

    /**
     * @covers \sCore::sBuildLink
     */
    public function testsBuildLink()
    {
        // Empty data will return empty string
        $request = $this->Request()->setParams([]);
        $this->Front()->setRequest($request);
        $this->assertEquals('', $this->module->sBuildLink([]));

        // Provided sVariables are passed into the url, except 'coreID' and 'sPartner'
        $sVariablesTestResult = $this->module->sBuildLink([
            'coreID' => 'foo',
            'sPartner' => 'bar',
            'some' => 'with',
            'other' => 'test',
            'variables' => 'values',
        ]);
        $this->assertInternalType('string', $sVariablesTestResult);
        $this->assertGreaterThan(0, strlen($sVariablesTestResult));

        $resultArray = [];
        parse_str(trim($sVariablesTestResult, '?'), $resultArray);
        $this->assertArrayHasKey('some', $resultArray);
        $this->assertArrayHasKey('other', $resultArray);
        $this->assertArrayHasKey('variables', $resultArray);
        $this->assertArrayNotHasKey('coreID', $resultArray);
        $this->assertArrayNotHasKey('sPartner', $resultArray);
        $this->assertEquals('with', $resultArray['some']);
        $this->assertEquals('test', $resultArray['other']);
        $this->assertEquals('values', $resultArray['variables']);

        // Provided sVariables override _GET, not overlapping get included from both
        // Also test that null values don't get passed on
        $request = $this->Request()->setParams([
            'just' => 'used',
            'some' => 'for',
            'variables' => 'testing',
            'nullGet' => null,
        ]);
        $this->Front()->setRequest($request);
        $sVariablesTestResult = $this->module->sBuildLink([
            'other' => 'with',
            'variables' => 'values',
            'nullVariables' => null,
        ]);
        $this->assertInternalType('string', $sVariablesTestResult);
        $this->assertGreaterThan(0, strlen($sVariablesTestResult));

        $resultArray = [];
        parse_str(trim($sVariablesTestResult, '?'), $resultArray);
        $this->assertArrayHasKey('just', $resultArray);
        $this->assertArrayHasKey('some', $resultArray);
        $this->assertArrayHasKey('other', $resultArray);
        $this->assertArrayHasKey('variables', $resultArray);
        $this->assertArrayNotHasKey('nullVariables', $resultArray);
        $this->assertArrayNotHasKey('nullGet', $resultArray);
        $this->assertEquals('used', $resultArray['just']);
        $this->assertEquals('for', $resultArray['some']);
        $this->assertEquals('with', $resultArray['other']);
        $this->assertEquals('values', $resultArray['variables']);

        // Test that sViewport=cat only keeps sCategory and sPage from GET
        // Test that they can still be overwriten by sVariables
        $request = $this->Request()->setParams([
            'sViewport' => 'cat',
            'sCategory' => 'getCategory',
            'sPage' => 'getPage',
            'foo' => 'bar',
        ]);
        $this->Front()->setRequest($request);
        $sVariablesTestResult = $this->module->sBuildLink([
            'sCategory' => 'sVariablesCategory',
            'other' => 'with',
            'variables' => 'values',
        ]);
        $this->assertInternalType('string', $sVariablesTestResult);
        $this->assertGreaterThan(0, strlen($sVariablesTestResult));

        $resultArray = [];
        parse_str(trim($sVariablesTestResult, '?'), $resultArray);
        $this->assertArrayHasKey('sViewport', $resultArray);
        $this->assertArrayHasKey('sCategory', $resultArray);
        $this->assertArrayHasKey('sPage', $resultArray);
        $this->assertArrayHasKey('other', $resultArray);
        $this->assertArrayHasKey('variables', $resultArray);
        $this->assertArrayNotHasKey('foo', $resultArray);
        $this->assertEquals('cat', $resultArray['sViewport']);
        $this->assertEquals('sVariablesCategory', $resultArray['sCategory']);
        $this->assertEquals('getPage', $resultArray['sPage']);
        $this->assertEquals('with', $resultArray['other']);
        $this->assertEquals('values', $resultArray['variables']);

        // Test that overriding sViewport doesn't override the special behavior
        $request = $this->Request()->setParams([
            'sViewport' => 'cat',
            'sCategory' => 'getCategory',
            'sPage' => 'getPage',
            'foo' => 'boo',
        ]);
        $this->Front()->setRequest($request);
        $sVariablesTestResult = $this->module->sBuildLink([
            'sViewport' => 'test',
            'sCategory' => 'sVariablesCategory',
            'other' => 'with',
            'variables' => 'values',
        ]);
        $this->assertInternalType('string', $sVariablesTestResult);
        $this->assertGreaterThan(0, strlen($sVariablesTestResult));

        $resultArray = [];
        parse_str(trim($sVariablesTestResult, '?'), $resultArray);
        $this->assertArrayHasKey('sViewport', $resultArray);
        $this->assertArrayHasKey('sCategory', $resultArray);
        $this->assertArrayHasKey('sPage', $resultArray);
        $this->assertArrayHasKey('other', $resultArray);
        $this->assertArrayHasKey('variables', $resultArray);
        $this->assertArrayNotHasKey('foo', $resultArray);
        $this->assertEquals('test', $resultArray['sViewport']);
        $this->assertEquals('sVariablesCategory', $resultArray['sCategory']);
        $this->assertEquals('getPage', $resultArray['sPage']);
        $this->assertEquals('with', $resultArray['other']);
        $this->assertEquals('values', $resultArray['variables']);
    }

    /**
     * @covers \sCore::sRewriteLink
     */
    public function testsRewriteLink()
    {
        // Call dispatch as we need the Router to be available inside sCore
        $this->dispatch('/');

        $baseUrl = $this->module->sRewriteLink();

        // Without arguments, we expect the base url
        $this->assertInternalType('string', $baseUrl);
        $this->assertGreaterThan(0, strlen($baseUrl));

        // Fetch all rows and test them
        $paths = Shopware()->Db()->fetchCol(
            'SELECT org_path FROM s_core_rewrite_urls WHERE subshopID = ?',
            [Shopware()->Shop()->getId()]
        );
        foreach ($paths as $path) {
            $expectedPath = Shopware()->Db()->fetchOne(
                'SELECT path FROM s_core_rewrite_urls WHERE subshopID = ? AND org_path = ? ORDER BY main DESC LIMIT 1',
                [Shopware()->Shop()->getId(), $path]
            );

            $this->assertEquals(strtolower($baseUrl . $expectedPath), $this->module->sRewriteLink('?' . $path));
            $this->assertEquals(strtolower($baseUrl . $expectedPath), $this->module->sRewriteLink('?' . $path, 'testTitle'));
        }
    }
}
