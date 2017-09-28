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

use Shopware\Framework\Struct\Struct;

abstract class Error extends Struct
{
    const LEVEL_WARNING = 0;

    const LEVEL_ERROR = 1;

    abstract public function getMessageKey(): string;

    abstract public function getMessage(): string;

    abstract public function getLevel(): int;

    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        $data['level'] = $this->getLevel();
        $data['message'] = $this->getMessage();
        $data['messageKey'] = $this->getMessageKey();

        return $data;
    }
}
