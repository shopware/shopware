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
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Routing\Router;
use Shopware\Product\Gateway\ProductRepository;
use Shopware\Product\Struct\ProductIdentity;
use Shopware\Search\Condition\ActiveCondition;
use Shopware\Search\Condition\CanonicalCondition;
use Shopware\Search\Condition\ForeignKeyCondition;
use Shopware\Search\Condition\MainVariantCondition;
use Shopware\Search\Condition\NameCondition;
use Shopware\Search\Condition\ShopCondition;
use Shopware\Search\Criteria;
use Shopware\SeoUrl\Gateway\SeoUrlRepository;
use Shopware\SeoUrl\Generator\SeoUrlGeneratorInterface;
use Shopware\SeoUrl\Struct\SeoUrl;
use Shopware\SeoUrl\Struct\SeoUrlCollection;

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

    public function fetch(int $shopId, TranslationContext $context, int $offset, int $limit): SeoUrlCollection
    {
        $criteria = new Criteria();
        $criteria->offset($offset);
        $criteria->limit($limit);

        $criteria->addCondition(new ShopCondition([$shopId]));
        $criteria->addCondition(new ActiveCondition(true));
        $criteria->addCondition(new MainVariantCondition());

        $result = $this->repository->search($criteria, $context);

        $products = $this->repository->read($result->getNumbers(), $context, ProductRepository::FETCH_MINIMAL);

        $criteria = new Criteria();
        $criteria->addCondition(new CanonicalCondition(true));
        $criteria->addCondition(new ForeignKeyCondition($products->getProductUuids()));
        $criteria->addCondition(new NameCondition([self::ROUTE_NAME]));
        $criteria->addCondition(new ShopCondition([$shopId]));
        $existingCanonicals = $this->seoUrlRepository->search($criteria, $context);

        $routes = new SeoUrlCollection();
        /** @var ProductIdentity $identity */
        foreach ($result as $identity) {
            if (!$product = $products->get($identity->getNumber())) {
                continue;
            }

            $pathInfo = $this->generator->generate(self::ROUTE_NAME, ['number' => $identity->getNumber()]);
            $seoPathInfo = $this->slugify->slugify($product->getName()) . '/' . $this->slugify->slugify($product->getNumber());

            if (!$seoPathInfo || !$pathInfo) {
                continue;
            }

            $routes->add(
                new SeoUrl(
                    null,
                    $shopId,
                    self::ROUTE_NAME,
                    $identity->getUuid(),
                    $pathInfo,
                    $seoPathInfo,
                    '',
                    new \DateTime(),
                    !$existingCanonicals->hasPathInfo($pathInfo)
                )
            );
        }

        return $routes;
    }

    public function getName(): string
    {
        return self::ROUTE_NAME;
    }
}
