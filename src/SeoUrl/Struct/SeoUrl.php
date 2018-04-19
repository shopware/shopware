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

namespace Shopware\SeoUrl\Struct;

use Shopware\Framework\Struct\Struct;

class SeoUrl extends Struct
{
    /**
     * @var int|null
     */
    protected $id;

    /**
     * @var int
     */
    protected $shopId;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $pathInfo;

    /**
     * @var string
     */
    protected $seoPathInfo;

    /**
     * @var string
     */
    protected $url;

    /**
     * @var bool
     */
    protected $isCanonical;

    /**
     * @var string
     */
    protected $seoHash;

    /**
     * @var int
     */
    protected $foreignKey;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    public function __construct(
        ?int $id,
        int $shopId,
        string $name,
        int $foreignKey,
        string $pathInfo,
        string $seoPathInfo,
        string $url,
        \DateTime $createdAt,
        bool $isCanonical = false
    ) {
        $this->id = $id;
        $this->pathInfo = '/' . trim($pathInfo, '/');
        $this->seoPathInfo = strtolower('/' . trim($seoPathInfo, '/'));
        $this->name = $name;
        $this->shopId = $shopId;
        $this->isCanonical = $isCanonical;
        $this->seoHash = self::createSeoHash($this->seoPathInfo);
        $this->foreignKey = $foreignKey;
        $this->createdAt = $createdAt;
        $this->url = $url;
    }

    public static function createSeoHash(string $seoPathInfo): string
    {
        return sha1($seoPathInfo);
    }

    public function getPathInfo(): string
    {
        return $this->pathInfo;
    }

    public function getSeoPathInfo(): string
    {
        return $this->seoPathInfo;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getShopId(): int
    {
        return $this->shopId;
    }

    public function isCanonical(): bool
    {
        return $this->isCanonical;
    }

    public function getForeignKey(): int
    {
        return $this->foreignKey;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getSeoHash(): string
    {
        return $this->seoHash;
    }

    public function getUrl(): string
    {
        return $this->url;
    }
}
