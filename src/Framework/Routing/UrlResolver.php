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

namespace Shopware\Framework\Routing;

use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Seo\Repository\SeoUrlRepository;
use Shopware\Api\Seo\Struct\SeoUrlBasicStruct;
use Shopware\Context\Struct\TranslationContext;

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

    public function getPathInfo(string $shopUuid, string $url, TranslationContext $context): ?SeoUrlBasicStruct
    {
        $url = ltrim($url, '/');

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('seo_url.shopUuid', $shopUuid));
        $criteria->addFilter(new TermQuery('seo_url.seoPathInfo', $url));
        $urls = $this->seoUrlRepository->search($criteria, $context);

        return $urls->getBySeoPathInfo($url);
    }

    public function getUrl(string $shopUuid, string $pathInfo, TranslationContext $context): ?SeoUrlBasicStruct
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('seo_url.shopUuid', $shopUuid));
        $criteria->addFilter(new TermQuery('seo_url.pathInfo', $pathInfo));
        $criteria->addFilter(new TermQuery('seo_url.isCanonical', true));

        $urls = $this->seoUrlRepository->search($criteria, $context);

        return $urls->getByPathInfo($pathInfo);
    }
}
