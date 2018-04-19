<?php
declare(strict_types=1);

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

namespace Shopware\Category\Struct;

use Shopware\Media\Struct\Media;
use Shopware\ProductStream\Struct\ProductStream;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class Category extends CategoryIdentity
{
    /**
     * @var Media|null
     */
    protected $media;

    /**
     * @var int[]
     */
    protected $blockedCustomerGroupIds = [];

    /**
     * @var null|ProductStream
     */
    protected $productStream;

    public function getMedia(): ?Media
    {
        return $this->media;
    }

    public function setMedia(?Media $media): void
    {
        $this->media = $media;
    }

    public function getBlockedCustomerGroupIds(): array
    {
        return $this->blockedCustomerGroupIds;
    }

    public function setBlockedCustomerGroupIds(array $blockedCustomerGroupIds): void
    {
        $this->blockedCustomerGroupIds = $blockedCustomerGroupIds;
    }

    public function getProductStream(): ?ProductStream
    {
        return $this->productStream;
    }

    public function setProductStream(?ProductStream $productStream): void
    {
        $this->productStream = $productStream;
    }
}
