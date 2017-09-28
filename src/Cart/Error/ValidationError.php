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

namespace Shopware\Cart\Error;

class ValidationError extends Error
{
    /**
     * @var int
     */
    protected $level;

    /**
     * @var string
     */
    protected $identifier;

    /**
     * @var string
     */
    protected $message;

    /**
     * @param int    $level
     * @param string $message
     * @param string $identifier
     */
    public function __construct(int $level, string $message, string $identifier)
    {
        $this->level = $level;
        $this->identifier = $identifier;
        $this->message = $message;
    }

    public function getMessageKey(): string
    {
        return 'validation';
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}
