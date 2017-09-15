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

namespace Shopware\Media\Struct;

use Shopware\Album\Struct\AlbumBasicCollection;
use Shopware\Framework\Struct\Collection;

class MediaBasicCollection extends Collection
{
    /**
     * @var MediaBasicStruct[]
     */
    protected $elements = [];

    public function add(MediaBasicStruct $media): void
    {
        $key = $this->getKey($media);
        $this->elements[$key] = $media;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(MediaBasicStruct $media): void
    {
        parent::doRemoveByKey($this->getKey($media));
    }

    public function exists(MediaBasicStruct $media): bool
    {
        return parent::has($this->getKey($media));
    }

    public function getList(array $uuids): MediaBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? MediaBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (MediaBasicStruct $media) {
            return $media->getUuid();
        });
    }

    public function getAlbumUuids(): array
    {
        return $this->fmap(function (MediaBasicStruct $media) {
            return $media->getAlbumUuid();
        });
    }

    public function filterByAlbumUuid(string $uuid): MediaBasicCollection
    {
        return $this->filter(function (MediaBasicStruct $media) use ($uuid) {
            return $media->getAlbumUuid() === $uuid;
        });
    }

    public function getUserUuids(): array
    {
        return $this->fmap(function (MediaBasicStruct $media) {
            return $media->getUserUuid();
        });
    }

    public function filterByUserUuid(string $uuid): MediaBasicCollection
    {
        return $this->filter(function (MediaBasicStruct $media) use ($uuid) {
            return $media->getUserUuid() === $uuid;
        });
    }

    public function getAlbums(): AlbumBasicCollection
    {
        return new AlbumBasicCollection(
            $this->fmap(function (MediaBasicStruct $media) {
                return $media->getAlbum();
            })
        );
    }

    protected function getKey(MediaBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
