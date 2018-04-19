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

namespace Shopware\Tests\Unit\Components\Filesystem;

use PHPUnit\Framework\TestCase;
use Shopware\Bundle\MediaBundle\Exception\StrategyNotFoundException;
use Shopware\Bundle\MediaBundle\Strategy\Md5Strategy;
use Shopware\Bundle\MediaBundle\Strategy\PlainStrategy;
use Shopware\Bundle\MediaBundle\StrategyFactory;

class StrategyFactoryTest extends TestCase
{
    public function testFactoryWithoutStrategies()
    {
        $this->expectException(StrategyNotFoundException::class);
        $this->expectExceptionMessage('Media strategy by name "md5" not found.');

        $factory = new StrategyFactory([]);
        $factory->factory('md5');
    }

    public function testFactoryWithSingleStrategyThatDoesNotMatch()
    {
        $this->expectException(StrategyNotFoundException::class);
        $this->expectExceptionMessage('Media strategy by name "md5" not found.');

        $factory = new StrategyFactory([
            new PlainStrategy(),
        ]);

        $factory->factory('md5');
    }

    public function testFactoryWithExistingStrategies()
    {
        $factory = $this->createCompleteFactory();
        $strategy = $factory->factory('md5');

        $this->assertInstanceOf(Md5Strategy::class, $strategy);
    }

    private function createCompleteFactory(): StrategyFactory
    {
        return new StrategyFactory([
            new Md5Strategy(),
            new PlainStrategy(),
        ]);
    }
}
