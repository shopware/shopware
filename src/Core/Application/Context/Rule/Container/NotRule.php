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

namespace Shopware\Application\Context\Rule\Container;

use Shopware\Application\Context\Rule\MatchContext\RuleMatchContext;
use Shopware\Application\Context\Rule\Match;
use Shopware\Application\Context\Rule\Rule;

/**
 * NotRule inverses the return value of the child rule. Only one child is possible
 */
class NotRule extends Container
{
    public function addRule(Rule $rule): void
    {
        parent::addRule($rule);
        $this->checkRules();
    }

    public function setRules(array $rules): void
    {
        parent::setRules(array_values($rules));
        $this->checkRules();
    }

    public function match(
        RuleMatchContext $matchContext
    ): Match {
        $rules = $this->rules;

        $rule = array_shift($rules);

        $match = $rule->match($matchContext);

        return new Match(
            !$match->matches(),
            $match->getMessages()
        );
    }

    /**
     * Enforce that NOT only handles ONE child rule
     *
     * @throws \RuntimeException
     */
    protected function checkRules(): void
    {
        if (count($this->rules) > 1) {
            throw new \RuntimeException('NOT rule can only hold one rule');
        }
    }
}
