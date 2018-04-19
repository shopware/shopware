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
class sRewriteTest extends PHPUnit\Framework\TestCase
{
    /**
     * @var sRewriteTable
     */
    private $rewriteTable;

    public function setUp()
    {
        $this->rewriteTable = Shopware()->Modules()->RewriteTable();
    }

    /**
     * * @dataProvider provider
     */
    public function testRewriteString($string, $result)
    {
        $this->assertEquals($result, $this->rewriteTable->sCleanupPath($string));
    }

    public function provider()
    {
        return [
            [' a  b ', 'a-b'],
            ['hello', 'hello'],
            ['Hello', 'Hello'],
            ['Hello World', 'Hello-World'],
            ['Hello-World', 'Hello-World'],
            ['Hello:World', 'Hello-World'],
            ['Hello,World', 'Hello-World'],
            ['Hello;World', 'Hello-World'],
            ['Hello&World', 'Hello-World'],
            ['Hello & World', 'Hello-World'],
            ['Hello.World.html', 'Hello.World.html'],
            ['Hello World.html', 'Hello-World.html'],
            ['Hello World!', 'Hello-World'],
            ['Hello World!.html', 'Hello-World.html'],
            ['Hello / World', 'Hello/World'],
            ['Hello/World', 'Hello/World'],
            ['H+e#l1l--o/W§o r.l:d)', 'H-e-l1l-o/W-o-r.l-d'],
            [': World', 'World'],
            ['Nguyễn Đăng Khoa', 'Nguyen-Dang-Khoa'],
            ['Ä ä Ö ö Ü ü ß', 'AE-ae-OE-oe-UE-ue-ss'],
            ['Á À á à É È é è Ó Ò ó ò Ñ ñ Ú Ù ú ù', 'A-A-a-a-E-E-e-e-O-O-o-o-N-n-U-U-u-u'],
            ['Â â Ê ê Ô ô Û û', 'A-a-E-e-O-o-U-u'],
            ['Â â Ê ê Ô ô Û 1', 'A-a-E-e-O-o-U-1'],
            ['Привет мир', 'Privet-mir'],
            ['Привіт світ', 'Privit-svit'],
            ['°¹²³@', '0123at'],
            ['Mórë thån wørds', 'More-thaan-woerds'],
            ['Блоґ їжачка', 'Blog-jizhachka'],
            ['фильм', 'film'],
            ['драма', 'drama'],
            ['ελληνικά', 'ellinika'],
            ['C’est du français !', 'C-est-du-francais'],
            ['Één jaar', 'Een-jaar'],
            ['tiếng việt rất khó', 'tieng-viet-rat-kho'],
        ];
    }
}
