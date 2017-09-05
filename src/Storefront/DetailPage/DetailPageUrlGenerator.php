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

namespace Shopware\Storefront\DetailPage;

use Cocur\Slugify\SlugifyInterface;
use Ramsey\Uuid\Uuid;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Routing\Router;
use Shopware\Product\ProductRepository;
use Shopware\Product\Struct\ProductBasicStruct;
use Shopware\Product\Struct\ProductIdentity;
use Shopware\Search\Condition\ActiveCondition;
use Shopware\Search\Condition\CanonicalCondition;
use Shopware\Search\Condition\CategoryUuidCondition;
use Shopware\Search\Condition\ForeignKeyCondition;
use Shopware\Search\Condition\IsCanonicalCondition;
use Shopware\Search\Condition\MainVariantCondition;
use Shopware\Search\Condition\NameCondition;
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

class DetailPageUrlGenerator implements SeoUrlGeneratorInterface
{
    const ROUTE_NAME = 'detail_page';

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
        $criteria->offset($offset);
        $criteria->limit($limit);

        $criteria->addCondition(new CategoryUuidCondition([$shop->getUuid()]));
        $criteria->addCondition(new ActiveCondition(true));

        $products = $this->repository->search($criteria, $context);

        $criteria = new Criteria();
        $criteria->addCondition(new IsCanonicalCondition(true));
        $criteria->addCondition(new ForeignKeyCondition($products->getUuids()));
        $criteria->addCondition(new NameCondition([self::ROUTE_NAME]));
        $criteria->addCondition(new ShopUuidCondition([$shop->getUuid()]));
        $existingCanonicals = $this->seoUrlRepository->search($criteria, $context);

        $routes = new SeoUrlBasicCollection();
        /** @var ProductBasicStruct $product */
        foreach ($products as $product) {
            $pathInfo = $this->generator->generate(self::ROUTE_NAME, ['uuid' => $product->getUuid()]);

            $seoPathInfo = $this->slugify->slugify($product->getName()) . '/' . $this->slugify->slugify($product->getUuid());

            if (!$seoPathInfo || !$pathInfo) {
                continue;
            }

            $url = new SeoUrlBasicStruct();
            $url->setUuid(Uuid::uuid4()->toString());
            $url->setShopUuid($shop->getUuid());
            $url->setName(self::ROUTE_NAME);
            $url->setForeignKey($product->getUuid());
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
