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

namespace Shopware\SeoUrl\Generator;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\Condition\ForeignKeyCondition;
use Shopware\Search\Condition\NameCondition;
use Shopware\Search\Condition\ShopCondition;
use Shopware\Search\Condition\ShopUuidCondition;
use Shopware\Search\Criteria;
use Shopware\SeoUrl\SeoUrlRepository;
use Shopware\SeoUrl\Struct\SeoUrl;
use Shopware\SeoUrl\Struct\SeoUrlBasicCollection;
use Shopware\SeoUrl\Struct\SeoUrlBasicStruct;
use Shopware\SeoUrl\Struct\SeoUrlCollection;
use Shopware\Shop\ShopRepository;
use Shopware\Shop\Struct\ShopBasicStruct;

class SeoUrlGeneratorRegistry
{
    const LIMIT = 200;

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

    public function __construct(array $generators, SeoUrlRepository $repository, Connection $connection, ShopRepository $shopRepository)
    {
        $this->generators = $generators;
        $this->repository = $repository;
        $this->connection = $connection;
        $this->shopRepository = $shopRepository;
    }

    public function generate(string $shopUuid, TranslationContext $context, bool $force): void
    {
        $shop = $this->shopRepository->read([$shopUuid], $context)->get($shopUuid);

        foreach ($this->generators as $generator) {
            $this->connection->transactional(
                function () use ($shop, $generator, $context, $force) {
                    $offset = 0;

                    while (count($urls = $generator->fetch($shop, $context, $offset, self::LIMIT))) {
                        if (!$force) {
                            $urls = $this->filterNoneExistingRoutes($shop, $context, $generator->getName(), $urls);
                        }

                        $this->repository->create($urls->getIterator()->getArrayCopy());

                        $offset += self::LIMIT;
                    }
                }
            );
        }
    }

    private function filterNoneExistingRoutes(ShopBasicStruct $shop, TranslationContext $context, string $name, SeoUrlBasicCollection $urls): SeoUrlBasicCollection
    {
        $criteria = new Criteria();

        $criteria->addCondition(new NameCondition([$name]));
        $criteria->addCondition(new ForeignKeyCondition($urls->getForeignKeys()));
        $criteria->addCondition(new ShopUuidCondition([$shop->getUuid()]));

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
}
