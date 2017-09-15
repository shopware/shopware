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

namespace Shopware\Product\Struct;

use Shopware\Category\Struct\CategoryBasicCollection;
use Shopware\ProductDetail\Struct\ProductDetailBasicCollection;
use Shopware\ProductVote\Struct\ProductVoteBasicCollection;

class ProductDetailStruct extends ProductBasicStruct
{
    /**
     * @var string[]
     */
    protected $detailUuids = [];

    /**
     * @var ProductDetailBasicCollection
     */
    protected $details;

    /**
     * @var string[]
     */
    protected $categoryUuids = [];

    /**
     * @var CategoryBasicCollection
     */
    protected $categories;

    /**
     * @var string[]
     */
    protected $voteUuids = [];

    /**
     * @var ProductVoteBasicCollection
     */
    protected $votes;

    public function __construct()
    {
        $this->details = new ProductDetailBasicCollection();
        $this->categories = new CategoryBasicCollection();
        $this->votes = new ProductVoteBasicCollection();
    }

    public function getDetailUuids(): array
    {
        return $this->detailUuids;
    }

    public function setDetailUuids(array $detailUuids): void
    {
        $this->detailUuids = $detailUuids;
    }

    public function getDetails(): ProductDetailBasicCollection
    {
        return $this->details;
    }

    public function setDetails(ProductDetailBasicCollection $details): void
    {
        $this->details = $details;
    }

    public function getCategoryUuids(): array
    {
        return $this->categoryUuids;
    }

    public function setCategoryUuids(array $categoryUuids): void
    {
        $this->categoryUuids = $categoryUuids;
    }

    public function getCategories(): CategoryBasicCollection
    {
        return $this->categories;
    }

    public function setCategories(CategoryBasicCollection $categories): void
    {
        $this->categories = $categories;
    }

    public function getVoteUuids(): array
    {
        return $this->voteUuids;
    }

    public function setVoteUuids(array $voteUuids): void
    {
        $this->voteUuids = $voteUuids;
    }

    public function getVotes(): ProductVoteBasicCollection
    {
        return $this->votes;
    }

    public function setVotes(ProductVoteBasicCollection $votes): void
    {
        $this->votes = $votes;
    }
}
