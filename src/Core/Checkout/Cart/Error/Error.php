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

namespace Shopware\Core\Checkout\Cart\Error;

use Shopware\Core\Framework\Struct\AssignArrayTrait;
use Shopware\Core\Framework\Struct\CreateFromTrait;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;

abstract class Error extends \Exception implements \JsonSerializable
{
    //allows json_encode and to decode object via json serializer
    use JsonSerializableTrait;

    //allows to assign array data to this object
    use AssignArrayTrait;

    //allows to create a new instance with all data of the provided object
    use CreateFromTrait;

    public const LEVEL_NOTICE = 0;

    public const LEVEL_WARNING = 10;

    public const LEVEL_ERROR = 20;

    abstract public function getIdentifier(): string;

    abstract public function getMessageKey(): string;

    abstract public function getLevel(): int;

    abstract public function blockOrder(): bool;

    public function jsonSerialize(): array
    {
        $data = get_object_vars($this);
        $data['identifier'] = $this->getIdentifier();
        $data['level'] = $this->getLevel();
        $data['message'] = $this->getMessage();
        $data['messageKey'] = $this->getMessageKey();

        return $data;
    }
}
