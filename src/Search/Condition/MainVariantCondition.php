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

namespace Shopware\Search\Condition;

use Shopware\Search\ConditionInterface;

class MainVariantCondition implements ConditionInterface
{
    const TYPE_ONLY_MAIN_VARIANT = 1;

    const TYPE_ONLY_SUB_VARIANTS = 2;

    /**
     * @var int
     */
    private $type;

    public function __construct(int $type = self::TYPE_ONLY_MAIN_VARIANT)
    {
        $this->type = $type;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function getName(): string
    {
        return self::class;
    }
}
