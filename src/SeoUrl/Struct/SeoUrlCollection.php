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

use Shopware\Framework\Struct\Collection;

class SeoUrlCollection extends Collection
{
    /**
     * @var SeoUrl[]
     */
    protected $elements = [];

    /**
     * @var string[]
     */
    protected $mapping = [];

    public function add(SeoUrl $route): void
    {
        $this->elements[] = $route;
        $this->mapping[$route->getPathInfo()] = $route->getSeoPathInfo();
    }

    public function getByPathInfo(string $pathInfo): ?SeoUrl
    {
        foreach ($this->elements as $element) {
            if ($element->getPathInfo() === $pathInfo) {
                return $element;
            }
        }

        return null;
    }

    public function getBySeoPathInfo(string $seoPathInfo): ?SeoUrl
    {
        foreach ($this->elements as $element) {
            if ($element->getSeoPathInfo() === $seoPathInfo) {
                return $element;
            }
        }

        return null;
    }

    public function hasPathInfo(string $pathInfo): bool
    {
        return array_key_exists($pathInfo, $this->mapping);
    }

    public function hasSeoPathInfo(string $seoPathInfo): bool
    {
        $mapping = array_flip($this->mapping);

        return array_key_exists($seoPathInfo, $mapping);
    }

    public function getMapping(): array
    {
        return $this->mapping;
    }

    public function clear(): void
    {
        parent::clear();
        $this->mapping = [];
    }

    public function getForeignKeys(): array
    {
        return $this->map(function (SeoUrl $url) {
            return $url->getForeignKey();
        });
    }
}
