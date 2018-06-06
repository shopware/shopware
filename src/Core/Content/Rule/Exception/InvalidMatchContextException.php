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

namespace Shopware\Core\Content\Rule\Exception;

class InvalidMatchContextException extends \Exception
{
    public const CODE = 200004;

    /**
     * @var string
     */
    protected $context;

    /**
     * @var string
     */
    protected $class;

    public function __construct(string $context, string $class)
    {
        $this->context = $context;
        $this->class = $class;
        parent::__construct(
            sprintf('Invalid match context %s in %s', $this->context, $this->class),
            self::CODE
        );
    }

    public function getContext(): string
    {
        return $this->context;
    }

    public function getClass(): string
    {
        return $this->class;
    }
}
