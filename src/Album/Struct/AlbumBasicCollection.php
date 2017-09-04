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

namespace Shopware\Album\Struct;

use Shopware\Framework\Struct\Collection;

class AlbumBasicCollection extends Collection
{
    /**
     * @var AlbumBasicStruct[]
     */
    protected $elements = [];

    public function add(AlbumBasicStruct $album): void
    {
        $key = $this->getKey($album);
        $this->elements[$key] = $album;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(AlbumBasicStruct $album): void
    {
        parent::doRemoveByKey($this->getKey($album));
    }

    public function exists(AlbumBasicStruct $album): bool
    {
        return parent::has($this->getKey($album));
    }

    public function getList(array $uuids): AlbumBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? AlbumBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (AlbumBasicStruct $album) {
            return $album->getUuid();
        });
    }

    public function getParentUuids(): array
    {
        return $this->fmap(function (AlbumBasicStruct $album) {
            return $album->getParentUuid();
        });
    }

    public function filterByParentUuid(string $uuid): AlbumBasicCollection
    {
        return $this->filter(function (AlbumBasicStruct $album) use ($uuid) {
            return $album->getParentUuid() === $uuid;
        });
    }

    protected function getKey(AlbumBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
