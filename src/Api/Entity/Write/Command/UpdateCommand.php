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

namespace Shopware\Api\Entity\Write\Command;

use Shopware\Api\Entity\EntityDefinition;

class UpdateCommand implements WriteCommandInterface
{
    /**
     * @var array
     */
    private $primaryKey;

    /**
     * @var array
     */
    private $payload;

    /**
     * @var string|EntityDefinition
     */
    private $definition;

    public function __construct(string $definition, array $pkData, array $payload)
    {
        $this->primaryKey = $pkData;
        $this->payload = $payload;
        $this->definition = $definition;
    }

    public function isValid(): bool
    {
        return (bool) count($this->payload);
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getDefinition(): string
    {
        return $this->definition;
    }

    public function getPrimaryKey(): array
    {
        return $this->primaryKey;
    }
}
