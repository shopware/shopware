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

namespace Shopware\Framework\ORM\Write\FieldException;

use Shopware\Core\Framework\ORM\Write\FieldException\InvalidFieldException;
use Shopware\Core\Framework\ORM\Write\FieldException\WriteFieldException;
use Throwable;

class InvalidJsonFieldException extends WriteFieldException
{
    private const CONCERN = 'validation-error';

    /**
     * @var InvalidFieldException[]
     */
    private $exceptions;

    /**
     * @var string
     */
    private $path;

    public function __construct(string $path, array $exceptions, $code = 0, Throwable $previous = null)
    {
        parent::__construct(
            sprintf('Caught %s validation errors.', count($exceptions)),
            $code,
            $previous
        );
        $this->path = $path;
        $this->exceptions = $exceptions;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getConcern(): string
    {
        return self::CONCERN;
    }

    public function getExceptions(): array
    {
        return $this->exceptions;
    }

    public function toArray(): array
    {
        exit('FOO');
    }
}
