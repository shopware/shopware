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

namespace Shopware\Storefront\Page\Listing;

use Cocur\Slugify\SlugifyInterface;
use Ramsey\Uuid\Uuid;
use Shopware\Api\Category\Collection\CategoryBasicCollection;
use Shopware\Api\Category\Repository\CategoryRepository;
use Shopware\Api\Category\Struct\CategoryBasicStruct;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\MatchQuery;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Entity\Search\Query\TermsQuery;
use Shopware\Api\Seo\Collection\SeoUrlBasicCollection;
use Shopware\Api\Seo\Repository\SeoUrlRepository;
use Shopware\Api\Seo\Struct\SeoUrlBasicStruct;
use Shopware\Api\Shop\Struct\ShopBasicStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Routing\Router;
use Shopware\Seo\Generator\SeoUrlGeneratorInterface;

class ListingPageUrlGenerator implements SeoUrlGeneratorInterface
{
    public const ROUTE_NAME = 'listing_page';

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
        $criteria->setOffset($offset);
        $criteria->setLimit($limit);

        $criteria->addFilter(new MatchQuery('category.path', '|' . $shop->getCategoryUuid() . '|'));
        $criteria->addFilter(new TermQuery('category.active', 1));
        $categories = $this->categoryRepository->search($criteria, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('seo_url.isCanonical', 1));
        $criteria->addFilter(new TermsQuery('seo_url.foreignKey', $categories->getUuids()));
        $criteria->addFilter(new TermQuery('seo_url.name', self::ROUTE_NAME));
        $criteria->addFilter(new TermQuery('seo_url.shopUuid', $shop->getUuid()));

        $existingCanonicals = $this->seoUrlRepository->search($criteria, $context);

        $routes = new SeoUrlBasicCollection();

        /** @var CategoryBasicStruct $category */
        foreach ($categories as $category) {
            $pathInfo = $this->generator->generate(self::ROUTE_NAME, ['uuid' => $category->getUuid()]);

            $seoPathInfo = $this->buildSeoUrl($category->getUuid(), $shop, $categories);

            if (!$seoPathInfo || !$pathInfo) {
                continue;
            }

            $seoPathInfo = rtrim($seoPathInfo, '/') . '/' . $category->getUuid();

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
        if ($category->getParentUuid() === null) {
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
