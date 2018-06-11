<?php declare(strict_types=1);
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

namespace Shopware\Core\Content\Media\Util\Strategy;

use Shopware\Core\Content\Media\Exception\StrategyNotFoundException;

class StrategyFactory implements StrategyFactoryInterface
{
    /**
     * @var StrategyInterface[]
     */
    private $strategies;

    /**
     * @param StrategyInterface[]|iterable $strategies
     */
    public function __construct(iterable $strategies)
    {
        $this->strategies = $strategies;
    }

    /**
     * {@inheritdoc}
     */
    public function factory(string $strategyName): StrategyInterface
    {
        return $this->findStrategyByName($strategyName);
    }

    /**
     * @param string $strategyName
     *
     * @throws StrategyNotFoundException
     *
     * @return StrategyInterface
     */
    private function findStrategyByName(string $strategyName): StrategyInterface
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->getName() === $strategyName) {
                return $strategy;
            }
        }

        throw StrategyNotFoundException::fromName($strategyName);
    }
}
