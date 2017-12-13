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

namespace Shopware\Api\Entity\Write\FieldException;

use Shopware\Framework\ShopwareException;

class WriteStackException extends \DomainException implements ShopwareException
{
    /**
     * @var WriteFieldException[]
     */
    private $exceptions;

    public function __construct(WriteFieldException ...$exceptions)
    {
        $this->exceptions = $exceptions;
        parent::__construct(sprintf('Mapping failed, got %s failure(s). %s', count($exceptions), print_r($this->toArray(), true)));
    }

    /**
     * @return WriteFieldException[]
     */
    public function getExceptions(): array
    {
        return $this->exceptions;
    }

    public function toArray(): array
    {
        $result = [];

        foreach ($this->exceptions as $exception) {
            if (!isset($result[$exception->getPath()])) {
                $result[$exception->getPath()] = [];
            }

            $result[$exception->getPath()][$exception->getConcern()] = $exception->toArray();
        }

        return $result;
    }
}
