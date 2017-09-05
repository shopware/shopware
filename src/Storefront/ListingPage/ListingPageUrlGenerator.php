<?php
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

namespace Shopware\Storefront\ListingPage;

use Cocur\Slugify\SlugifyInterface;
use Ramsey\Uuid\Uuid;
use Shopware\Category\CategoryRepository;
use Shopware\Category\Struct\CategoryBasicCollection;
use Shopware\Category\Struct\CategoryBasicStruct;
use Shopware\Category\Struct\CategoryIdentity;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Routing\Router;
use Shopware\Search\Condition\ActiveCondition;
use Shopware\Search\Condition\CanonicalCondition;
use Shopware\Search\Condition\ForeignKeyCondition;
use Shopware\Search\Condition\IsCanonicalCondition;
use Shopware\Search\Condition\NameCondition;
use Shopware\Search\Condition\ParentUuidCondition;
use Shopware\Search\Condition\ShopCondition;
use Shopware\Search\Condition\ShopUuidCondition;
use Shopware\Search\Criteria;
use Shopware\SeoUrl\Generator\SeoUrlGeneratorInterface;
use Shopware\SeoUrl\SeoUrlRepository;
use Shopware\SeoUrl\Struct\SeoUrl;
use Shopware\SeoUrl\Struct\SeoUrlBasicCollection;
use Shopware\SeoUrl\Struct\SeoUrlBasicStruct;
use Shopware\SeoUrl\Struct\SeoUrlCollection;
use Shopware\Shop\Struct\ShopBasicStruct;

class ListingPageUrlGenerator implements SeoUrlGeneratorInterface
{
    const ROUTE_NAME = 'listing_page';

    /**
     * @var CategoryRepository
     */
    private $categoryRepository;

    /**
     * @var SlugifyInterface
     */
    private $slugify;

    /**
     * @var Router
     */
    private $generator;

    /**
     * @var SeoUrlRepository
     */
    private $seoUrlRepository;

    public function __construct(
        CategoryRepository $categoryRepository,
        SlugifyInterface $slugify,
        Router $generator,
        SeoUrlRepository $seoUrlRepository
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->slugify = $slugify;
        $this->generator = $generator;
        $this->seoUrlRepository = $seoUrlRepository;
    }

    public function fetch(ShopBasicStruct $shop, TranslationContext $context, int $offset, int $limit): SeoUrlBasicCollection
    {
        $criteria = new Criteria();
        $criteria->offset($offset);
        $criteria->limit($limit);
        $criteria->addCondition(new ParentUuidCondition([$shop->getCategoryUuid()]));
        $criteria->addCondition(new ActiveCondition(true));

        $categories = $this->categoryRepository->search($criteria, $context);

        $criteria = new Criteria();
        $criteria->addCondition(new IsCanonicalCondition(true));
        $criteria->addCondition(new ForeignKeyCondition($categories->getUuids()));
        $criteria->addCondition(new NameCondition([self::ROUTE_NAME]));
        $criteria->addCondition(new ShopUuidCondition([$shop->getUuid()]));

        $existingCanonicals = $this->seoUrlRepository->search($criteria, $context);

        $routes = new SeoUrlBasicCollection();

        /** @var CategoryBasicStruct $category */
        foreach ($categories as $category) {
            $pathInfo = $this->generator->generate(self::ROUTE_NAME, ['uuid' => $category->getUuid()]);

            $seoPathInfo = $this->buildSeoUrl($category->getId(), $shop, $categories);

            if (!$seoPathInfo || !$pathInfo) {
                continue;
            }

            $seoPathInfo = rtrim($seoPathInfo, '/') . '/' . $category->getId();

            $url = new SeoUrlBasicStruct();
            $url->setUuid(Uuid::uuid4()->toString());
            $url->setShopUuid($shop->getUuid());
            $url->setName(self::ROUTE_NAME);
            $url->setForeignKey($category->getUuid());
            $url->setPathInfo($pathInfo);
            $url->setSeoPathInfo($seoPathInfo);
            $url->setCreatedAt(new \DateTime());
            $url->setIsCanonical(!$existingCanonicals->hasPathInfo($pathInfo));
            $routes->add($url);
        }

        return $routes;
    }

    public function getName(): string
    {
        return self::ROUTE_NAME;
    }

    private function buildSeoUrl(?string $uuid, ShopBasicStruct $shop, CategoryBasicCollection $categories): ?string
    {
        $category = $categories->get($uuid);
        if (!$category) {
            return null;
        }
        if ($category->getUuid() === $shop->getCategoryUuid()) {
            return null;
        }
        if (!$category->getParentUuid()) {
            return null;
        }

        $name = $this->slugify->slugify($category->getName());

        $parent = $this->buildSeoUrl($category->getParentUuid(), $shop, $categories);

        if (!$parent) {
            return $name . '/';
        }

        return $parent . $name . '/';
    }
}
