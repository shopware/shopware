<?php

declare(strict_types=1);
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

namespace Shopware\Core\Checkout\Rule\Specification\Container;

use Shopware\Core\Checkout\Rule\Specification\Rule;

/**
 * AbstractContainer implements setRule and addRule of the container interface
 */
abstract class Container extends Rule
{
    /**
     * @var Rule[]
     */
    protected $rules = [];

    /**
     * Constructor params will be used for internal rules
     *
     * new ConcreteContainer(
     *      new TrueRule,
     *      new FalseRule,
     * )
     *
     * @param \Shopware\Core\Checkout\Rule\Specification\Rule[] $rules
     */
    public function __construct(array $rules = [])
    {
        array_map([$this, 'addRule'], $rules);
    }

    public function setRules(array $rules): void
    {
        $this->rules = $rules;
    }

    public function addRule(Rule $rule): void
    {
        $this->rules[] = $rule;
    }

    public function getRules(): array
    {
        return $this->rules;
    }
}
