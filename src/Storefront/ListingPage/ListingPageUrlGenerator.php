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
use Doctrine\DBAL\Connection;
use Shopware\Category\Gateway\CategoryRepository;
use Shopware\Category\Struct\CategoryIdentity;
use Shopware\Category\Struct\CategoryIdentityCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Routing\Router;
use Shopware\Search\Condition\ActiveCondition;
use Shopware\Search\Condition\CanonicalCondition;
use Shopware\Search\Condition\ForeignKeyCondition;
use Shopware\Search\Condition\NameCondition;
use Shopware\Search\Condition\ShopCondition;
use Shopware\Search\Criteria;
use Shopware\SeoUrl\Gateway\SeoUrlRepository;
use Shopware\SeoUrl\Generator\SeoUrlGeneratorInterface;
use Shopware\SeoUrl\Struct\SeoUrl;
use Shopware\SeoUrl\Struct\SeoUrlCollection;

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
        Connection $connection,
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

    public function fetch(int $shopId, TranslationContext $context, int $offset, int $limit): SeoUrlCollection
    {
        $criteria = new Criteria();
        $criteria->offset($offset);
        $criteria->limit($limit);
        $criteria->addCondition(new ShopCondition([$shopId]));
        $criteria->addCondition(new ActiveCondition(true));

        $result = $this->categoryRepository->search($criteria, $context);
        $categories = $this->categoryRepository->read($result->getIdsIncludingPaths(), $context, CategoryRepository::FETCH_IDENTITY);

        $criteria = new Criteria();
        $criteria->addCondition(new CanonicalCondition(true));
        $criteria->addCondition(new ForeignKeyCondition($categories->getIds()));
        $criteria->addCondition(new NameCondition([self::ROUTE_NAME]));
        $criteria->addCondition(new ShopCondition([$shopId]));

        $existingCanonicals = $this->seoUrlRepository->search($criteria, $context);

        $routes = new SeoUrlCollection();
        /** @var CategoryIdentity $identity */
        foreach ($result as $identity) {
            $pathInfo = $this->generator->generate(self::ROUTE_NAME, ['id' => $identity->getId()]);

            $seoPathInfo = $this->buildSeoUrl($identity->getId(), $categories);

            if (!$seoPathInfo || !$pathInfo) {
                continue;
            }

            $seoPathInfo = rtrim($seoPathInfo, '/') . '/' . $identity->getId();

            $routes->add(
                new SeoUrl(
                    null,
                    $shopId,
                    self::ROUTE_NAME,
                    $identity->getId(),
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

    private function buildSeoUrl(?int $id, CategoryIdentityCollection $categories): ?string
    {
        $category = $categories->get($id);
        if (!$category->getParent() || $category->isShopCategory()) {
            return null;
        }

        $name = $this->slugify->slugify($category->getName());

        $parent = $this->buildSeoUrl($category->getParent(), $categories);

        if (!$parent) {
            return $name . '/';
        }

        return $parent . $name . '/';
    }
}
