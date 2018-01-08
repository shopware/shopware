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

namespace Shopware\Storefront\Page\Detail;

use Cocur\Slugify\SlugifyInterface;
use Ramsey\Uuid\Uuid;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Entity\Search\Query\TermsQuery;
use Shopware\Api\Product\Repository\ProductRepository;
use Shopware\Api\Product\Struct\ProductBasicStruct;
use Shopware\Api\Seo\Collection\SeoUrlBasicCollection;
use Shopware\Api\Seo\Repository\SeoUrlRepository;
use Shopware\Api\Seo\Struct\SeoUrlBasicStruct;
use Shopware\Api\Shop\Struct\ShopBasicStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Routing\Router;
use Shopware\Seo\Generator\SeoUrlGeneratorInterface;

class DetailPageUrlGenerator implements SeoUrlGeneratorInterface
{
    public const ROUTE_NAME = 'detail_page';

    /**
     * @var ProductRepository
     */
    private $repository;

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
        ProductRepository $repository,
        SlugifyInterface $slugify,
        Router $generator,
        SeoUrlRepository $seoUrlRepository
    ) {
        $this->repository = $repository;
        $this->slugify = $slugify;
        $this->generator = $generator;
        $this->seoUrlRepository = $seoUrlRepository;
    }

    public function fetch(ShopBasicStruct $shop, TranslationContext $context, int $offset, int $limit): SeoUrlBasicCollection
    {
        $criteria = new Criteria();
        $criteria->setOffset($offset);
        $criteria->setLimit($limit);
        $criteria->addFilter(new TermQuery('product.categoryTree', $shop->getCategoryId()));
        $criteria->addFilter(new TermQuery('product.active', 1));
        $products = $this->repository->search($criteria, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('seo_url.isCanonical', 1));
        $criteria->addFilter(new TermsQuery('seo_url.foreignKey', $products->getIds()));
        $criteria->addFilter(new TermQuery('seo_url.name', self::ROUTE_NAME));
        $criteria->addFilter(new TermQuery('seo_url.shopId', $shop->getId()));
        $existingCanonicals = $this->seoUrlRepository->search($criteria, $context);

        $routes = new SeoUrlBasicCollection();
        /** @var ProductBasicStruct $product */
        foreach ($products as $product) {
            $pathInfo = $this->generator->generate(self::ROUTE_NAME, ['id' => $product->getId()]);

            $seoPathInfo = $this->slugify->slugify($product->getName());

            if (!$seoPathInfo || !$pathInfo) {
                continue;
            }

            $url = new SeoUrlBasicStruct();
            $url->setId(Uuid::uuid4()->toString());
            $url->setShopId($shop->getId());
            $url->setName(self::ROUTE_NAME);
            $url->setForeignKey($product->getId());
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
}
