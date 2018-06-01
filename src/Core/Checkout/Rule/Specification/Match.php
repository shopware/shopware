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

namespace Shopware\Core\Checkout\Rule\Specification;

use Shopware\Core\Framework\Struct\Struct;

class Match extends Struct
{
    /**
     * @var bool
     */
    protected $match;

    /**
     * @var array
     */
    private $messages;

    /**
     * @param bool  $match
     * @param array $messages
     */
    public function __construct(bool $match, array $messages = [])
    {
        $this->match = $match;
        $this->messages = $messages;
    }

    public function __invoke()
    {
        return $this->match;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function matches(): bool
    {
        return $this->match;
    }
}
