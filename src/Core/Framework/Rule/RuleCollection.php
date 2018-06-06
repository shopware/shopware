<?php declare(strict_types=1);
/**
 * Shopware\Core 5
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
 * "Shopware\Core" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\Rule\Container\Container;
use Shopware\Core\Framework\Struct\Collection;

class RuleCollection extends Collection
{
    /**
     * @var Rule[]
     */
    protected $elements = [];

    /**
     * @var Rule[]
     */
    protected $flat = [];

    /**
     * @var bool[]
     */
    protected $classes = [];

    public function add(Rule $rule): void
    {
        parent::doAdd($rule);

        $this->addMeta($rule);
    }

    public function clear(): void
    {
        parent::clear();

        $this->flat = [];

        $this->classes = [];
    }

    /**
     * @param string $class
     *
     * @return Collection|RuleCollection
     */
    public function filterInstance(string $class): Collection
    {
        return new self(
            array_filter(
                $this->flat,
                function (Rule $rule) use ($class) {
                    return $rule instanceof $class;
                }
            )
        );
    }

    public function has($class): bool
    {
        return array_key_exists($class, $this->classes);
    }

    private function addMeta(Rule $rule): void
    {
        $this->classes[get_class($rule)] = true;

        $this->flat[] = $rule;

        if ($rule instanceof Container) {
            array_map([$this, 'addMeta'], $rule->getRules());
        }
    }
}
