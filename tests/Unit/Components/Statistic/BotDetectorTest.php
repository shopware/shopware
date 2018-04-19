<?php
declare(strict_types=1);
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

namespace Shopware\Tests\Unit\Components\Statistic;

use PHPUnit\Framework\TestCase;
use Shopware\Components\Statistic\BotDetector;

class BotDetectorTest extends TestCase
{
    /**
     * @dataProvider botList
     *
     * @param string $blacklist
     * @param string $agent
     * @param bool   $isBot
     */
    public function testBotRequest(string $blacklist, string $agent, bool $isBot)
    {
        $request = $this->getRequest($agent);
        $config = $this->getConfig($blacklist);

        $detector = new BotDetector($config);

        $this->assertSame(
            $isBot,
            $detector->isBotRequest($request)
        );
    }

    public function botList()
    {
        return [
            ['', '', false],
            ['', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/56.0.2924.87 Safari/537.36', false],
            ['mozillamacintoshintelmacosxapplewebkitkhtmllikegeckochromesafari', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/56.0.2924.87 Safari/537.36', true],
            ['mozilla', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/56.0.2924.87 Safari/537.36', true],
            ['10_12_3', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/56.0.2924.87 Safari/537.36', false],
            ['10_12_3', '10_12_3', false],
            ['10_12_3', '', false],
            ['', '10_12_3', false],
            ['mozilla;chrome;safari', 'chrome', true],
        ];
    }

    private function getConfig($blacklist)
    {
        $config = $this->createMock(\Shopware_Components_Config::class);
        $config->method('get')
            ->with('botBlackList')
            ->will($this->returnValue($blacklist));

        return $config;
    }

    private function getRequest($agent)
    {
        $request = $this->createMock(\Enlight_Controller_Request_RequestHttp::class);
        $request->method('getHeader')
            ->with('USER_AGENT')
            ->will($this->returnValue($agent));

        return $request;
    }
}
