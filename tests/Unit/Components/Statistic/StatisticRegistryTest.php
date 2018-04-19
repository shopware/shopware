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

namespace Shopware\Tests\Unit\Components\Statistic;

use PHPUnit\Framework\TestCase;
use Shopware\Context\Struct\ShopContext;
use Shopware\Components\Statistic\BotDetector;
use Shopware\Components\Statistic\StatisticRegistry;
use Shopware\Components\Statistic\StatisticTracerInterface;

class StatisticRegistryTest extends TestCase
{
    public function testTraceRequest()
    {
        $tracer = $this->createMock(StatisticTracerInterface::class);
        $tracer->expects($this->exactly(1))
            ->method('traceRequest');

        $registry = new StatisticRegistry(
            [$tracer],
            $this->getConfig(null),
            $this->getBotDetector(false)
        );

        $registry->traceRequest(
            $this->getRequest('0.0.0.0'),
            $this->createMock(ShopContext::class)
        );
    }

    public function testWithoutIp()
    {
        $tracer = $this->createMock(StatisticTracerInterface::class);
        $tracer->expects($this->never())
            ->method('traceRequest');

        $registry = new StatisticRegistry(
            [$tracer],
            $this->getConfig(null),
            $this->getBotDetector(false)
        );

        $registry->traceRequest(
            $this->getRequest(null),
            $this->createMock(ShopContext::class)
        );
    }

    public function testWithBlockedIp()
    {
        $tracer = $this->createMock(StatisticTracerInterface::class);
        $tracer->expects($this->never())
            ->method('traceRequest');

        $registry = new StatisticRegistry(
            [$tracer],
            $this->getConfig('0.0.0.0'),
            $this->getBotDetector(false)
        );

        $registry->traceRequest(
            $this->getRequest('0.0.0.0'),
            $this->createMock(ShopContext::class)
        );
    }

    public function testWithBlockedIpList()
    {
        $tracer = $this->createMock(StatisticTracerInterface::class);
        $tracer->expects($this->never())
            ->method('traceRequest');

        $registry = new StatisticRegistry(
            [$tracer],
            $this->getConfig('127.0.0.11, 127.0.0.1'),
            $this->getBotDetector(false)
        );

        $registry->traceRequest(
            $this->getRequest('127.0.0.1'),
            $this->createMock(ShopContext::class)
        );
    }

    public function testIpWithUnblockedIpList()
    {
        $tracer = $this->createMock(StatisticTracerInterface::class);
        $tracer->expects($this->exactly(1))
            ->method('traceRequest');

        $registry = new StatisticRegistry(
            [$tracer],
            $this->getConfig('127.0.0.11, 127.0.0.1'),
            $this->getBotDetector(false)
        );

        $registry->traceRequest(
            $this->getRequest('127.0.0.3'),
            $this->createMock(ShopContext::class)
        );
    }

    public function testWithBotDetection()
    {
        $tracer = $this->createMock(StatisticTracerInterface::class);
        $tracer->expects($this->never())
            ->method('traceRequest');

        $registry = new StatisticRegistry(
            [$tracer],
            $this->getConfig(null),
            $this->getBotDetector(true)
        );

        $registry->traceRequest(
            $this->getRequest(null),
            $this->createMock(ShopContext::class)
        );
    }

    private function getBotDetector(bool $isBot)
    {
        $detector = $this->createMock(BotDetector::class);
        $detector->method('isBotRequest')
            ->will($this->returnValue($isBot));

        return $detector;
    }

    private function getRequest(?string $clientIp)
    {
        $request = $this->createMock(\Enlight_Controller_Request_RequestHttp::class);
        $request->method('getClientIp')
            ->will($this->returnValue($clientIp));

        return $request;
    }

    private function getConfig(?string $blockIp)
    {
        $config = $this->createMock(\Shopware_Components_Config::class);
        $config->method('get')
            ->will($this->returnValue($blockIp));

        return $config;
    }
}
