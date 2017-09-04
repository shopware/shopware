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

use Shopware\Framework\Struct\Struct;

class AlbumBasicStruct extends Struct
{
    /**
     * @var string
     */
    protected $uuid;

    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $parentUuid;

    /**
     * @var int|null
     */
    protected $parentId;

    /**
     * @var int
     */
    protected $position;

    /**
     * @var int
     */
    protected $createThumbnails;

    /**
     * @var string
     */
    protected $thumbnailSize;

    /**
     * @var string
     */
    protected $icon;

    /**
     * @var bool
     */
    protected $thumbnailHighDpi;

    /**
     * @var int|null
     */
    protected $thumbnailQuality;

    /**
     * @var int|null
     */
    protected $thumbnailHighDpiQuality;

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getParentUuid(): ?string
    {
        return $this->parentUuid;
    }

    public function setParentUuid(?string $parentUuid): void
    {
        $this->parentUuid = $parentUuid;
    }

    public function getParentId(): ?int
    {
        return $this->parentId;
    }

    public function setParentId(?int $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getCreateThumbnails(): int
    {
        return $this->createThumbnails;
    }

    public function setCreateThumbnails(int $createThumbnails): void
    {
        $this->createThumbnails = $createThumbnails;
    }

    public function getThumbnailSize(): string
    {
        return $this->thumbnailSize;
    }

    public function setThumbnailSize(string $thumbnailSize): void
    {
        $this->thumbnailSize = $thumbnailSize;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function setIcon(string $icon): void
    {
        $this->icon = $icon;
    }

    public function getThumbnailHighDpi(): bool
    {
        return $this->thumbnailHighDpi;
    }

    public function setThumbnailHighDpi(bool $thumbnailHighDpi): void
    {
        $this->thumbnailHighDpi = $thumbnailHighDpi;
    }

    public function getThumbnailQuality(): ?int
    {
        return $this->thumbnailQuality;
    }

    public function setThumbnailQuality(?int $thumbnailQuality): void
    {
        $this->thumbnailQuality = $thumbnailQuality;
    }

    public function getThumbnailHighDpiQuality(): ?int
    {
        return $this->thumbnailHighDpiQuality;
    }

    public function setThumbnailHighDpiQuality(?int $thumbnailHighDpiQuality): void
    {
        $this->thumbnailHighDpiQuality = $thumbnailHighDpiQuality;
    }
}
