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

namespace Shopware\Seo\Generator;

use Doctrine\DBAL\Connection;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Entity\Search\Query\TermsQuery;
use Shopware\Api\Seo\Collection\SeoUrlBasicCollection;
use Shopware\Api\Seo\Repository\SeoUrlRepository;
use Shopware\Api\Seo\Struct\SeoUrlBasicStruct;
use Shopware\Api\Shop\Repository\ShopRepository;
use Shopware\Api\Shop\Struct\ShopBasicStruct;
use Shopware\Context\Struct\TranslationContext;

class SeoUrlGeneratorRegistry
{
    public const LIMIT = 200;

    /**
     * @var SeoUrlGeneratorInterface[]
     */
    private $generators;

    /**
     * @var SeoUrlRepository
     */
    private $repository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var ShopRepository
     */
    private $shopRepository;

    public function __construct(
        array $generators,
        SeoUrlRepository $repository,
        Connection $connection,
        ShopRepository $shopRepository
    ) {
        $this->generators = $generators;
        $this->repository = $repository;
        $this->connection = $connection;
        $this->shopRepository = $shopRepository;
    }

    public function generate(string $shopId, TranslationContext $context, bool $force): void
    {
        $shop = $this->shopRepository->readBasic([$shopId], $context)->get($shopId);

        foreach ($this->generators as $generator) {
            $this->connection->transactional(
                function () use ($shop, $generator, $context, $force) {
                    $offset = 0;

                    $urls = $generator->fetch($shop, $context, $offset, self::LIMIT);

                    while ($urls->count() > 0) {
                        if (!$force) {
                            $urls = $this->filterNoneExistingRoutes($shop, $context, $generator->getName(), $urls);
                        }

                        $data = $this->convert($urls);
                        if (!empty($data)) {
                            $this->repository->create($data, $context);
                        }

                        $offset += self::LIMIT;
                        $urls = $generator->fetch($shop, $context, $offset, self::LIMIT);
                    }
                }
            );
        }
    }

    private function filterNoneExistingRoutes(
        ShopBasicStruct $shop,
        TranslationContext $context,
        string $name,
        SeoUrlBasicCollection $urls
    ): SeoUrlBasicCollection {
        $criteria = new Criteria();

        $criteria->addFilter(new TermQuery('seo_url.name', $name));
        $criteria->addFilter(new TermsQuery('seo_url.foreignKey', $urls->getForeignKeys()));
        $criteria->addFilter(new TermQuery('seo_url.shopId', $shop->getId()));

        $existing = $this->repository->search($criteria, $context);

        $newUrls = new SeoUrlBasicCollection();

        /** @var SeoUrlBasicStruct $url */
        foreach ($urls as $url) {
            if ($existing->hasForeignKey($name, $url->getForeignKey())) {
                continue;
            }
            $newUrls->add($url);
        }

        return $newUrls;
    }

    private function convert(SeoUrlBasicCollection $urls): array
    {
        $data = [];
        /** @var SeoUrlBasicStruct $url */
        foreach ($urls as $url) {
            $row = json_decode(json_encode($url), true);
            unset($row['createdAt'], $row['updatedAt']);
            $data[] = $row;
        }

        return $data;
    }
}
