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

namespace Shopware\Category\Event;

use Shopware\Category\Struct\CategoryDetailCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Event\CustomerGroupBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Media\Event\MediaBasicLoadedEvent;
use Shopware\Product\Event\ProductBasicLoadedEvent;
use Shopware\ProductStream\Event\ProductStreamBasicLoadedEvent;

class CategoryDetailLoadedEvent extends NestedEvent
{
    const NAME = 'category.detail.loaded';

    /**
     * @var CategoryDetailCollection
     */
    protected $categories;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(CategoryDetailCollection $categories, TranslationContext $context)
    {
        $this->categories = $categories;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getCategories(): CategoryDetailCollection
    {
        return $this->categories;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
            new CategoryBasicLoadedEvent($this->categories, $this->context),
            new ProductStreamBasicLoadedEvent($this->categories->getProductStreams(), $this->context),
            new MediaBasicLoadedEvent($this->categories->getMedias(), $this->context),
            new ProductBasicLoadedEvent($this->categories->getProducts(), $this->context),
            new CustomerGroupBasicLoadedEvent($this->categories->getBlockedCustomerGroupss(), $this->context),
        ]);
    }
}
