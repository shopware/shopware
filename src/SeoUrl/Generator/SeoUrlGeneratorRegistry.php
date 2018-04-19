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
use Shopware\Search\Criteria;
use Shopware\SeoUrl\Gateway\SeoUrlRepository;
use Shopware\SeoUrl\Struct\SeoUrl;
use Shopware\SeoUrl\Struct\SeoUrlCollection;

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

    public function __construct(array $generators, SeoUrlRepository $repository, Connection $connection)
    {
        $this->generators = $generators;
        $this->repository = $repository;
        $this->connection = $connection;
    }

    public function generate(int $shopId, TranslationContext $context, bool $force): void
    {
        foreach ($this->generators as $generator) {
            $this->connection->transactional(
                function () use ($shopId, $generator, $context, $force) {
                    $offset = 0;

                    while (count($urls = $generator->fetch($shopId, $context, $offset, self::LIMIT))) {
                        if (!$force) {
                            $urls = $this->filterNoneExistingRoutes(
                                $shopId,
                                $context,
                                $generator->getName(),
                                $urls
                            );
                        }

                        $this->repository->create($urls->getIterator()->getArrayCopy());

                        $offset += self::LIMIT;
                    }
                }
            );
        }
    }

    private function filterNoneExistingRoutes(int $shopId, TranslationContext $context, string $name, SeoUrlCollection $urls): SeoUrlCollection
    {
        $criteria = new Criteria();

        $criteria->addCondition(new NameCondition([$name]));
        $criteria->addCondition(new ForeignKeyCondition($urls->getForeignKeys()));
        $criteria->addCondition(new ShopCondition([$shopId]));

        $existing = $this->repository->search($criteria, $context);

        $newUrls = new SeoUrlCollection();

        /** @var SeoUrl $url */
        foreach ($urls as $url) {
            if ($existing->hasForeignKey($name, $url->getForeignKey())) {
                continue;
            }
            $newUrls->add($url);
        }

        return $newUrls;
    }
}
