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

namespace Shopware\Framework\Routing;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\Condition\IsCanonicalCondition;
use Shopware\Search\Condition\PathInfoCondition;
use Shopware\Search\Condition\SeoPathInfoCondition;
use Shopware\Search\Condition\ShopUuidCondition;
use Shopware\Search\Criteria;
use Shopware\SeoUrl\SeoUrlRepository;
use Shopware\SeoUrl\Struct\SeoUrl;

class UrlResolver implements UrlResolverInterface
{
    /**
     * @var SeoUrlRepository
     */
    private $seoUrlRepository;

    public function __construct(SeoUrlRepository $seoUrlRepository)
    {
        $this->seoUrlRepository = $seoUrlRepository;
    }

    public function getPathInfo(string $shopUuid, string $url): ?SeoUrl
    {
        $criteria = new Criteria();
        $criteria->addCondition(new ShopUuidCondition([$shopUuid]));
        $criteria->addCondition(new SeoPathInfoCondition([$url]));

        $context = new TranslationContext(1, true, null);

        $urls = $this->seoUrlRepository->search($criteria, $context);

        return $urls->getBySeoPathInfo($url);
    }

    public function getUrl(string $shopUuid, string $pathInfo): ?SeoUrl
    {
        $criteria = new Criteria();
        $criteria->addCondition(new ShopUuidCondition([$shopUuid]));
        $criteria->addCondition(new PathInfoCondition([$pathInfo]));
        $criteria->addCondition(new IsCanonicalCondition(true));

        $context = new TranslationContext(1, true, null);

        $urls = $this->seoUrlRepository->search($criteria, $context);

        return $urls->getByPathInfo($pathInfo);
    }
}
