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

namespace Shopware\Denormalization\Command;

use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\Criteria;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class IndexProductsCommand extends ContainerAwareCommand
{
    const LIMIT = 10;

    /**
     * @var SymfonyStyle
     */
    private $io;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('denormalize:index:products')
            ->setDescription('Refreshs the product index');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        $context = $this->getTranslationContext('SWAG-SHOP-UUID-1');

        $productRepo = $this->getContainer()->get('shopware.product.repository');

        $indexer = $this->getContainer()->get('shopware.denormalization.product.product_indexer');

        $criteria = new Criteria();
        $criteria->setOffset(0);
        $criteria->setLimit(self::LIMIT);

        $this->io->comment('Refreshing products');

        $products = $productRepo->searchUuids($criteria, $context);

        $this->io->progressStart($products->getTotal());

        do {
            $indexer->index($products->getUuids(), $context);

            $this->io->progressAdvance(count($products->getUuids()));

            $criteria->setOffset($criteria->getOffset() + self::LIMIT);

            $products = $productRepo->searchUuids($criteria, $context);
        } while (count($products->getUuids()) > 0);

        $this->io->progressFinish();
        $this->io->success('Products refreshed successfully');
    }

    private function getTranslationContext(string $shopUuid): TranslationContext
    {
        /** @var QueryBuilder $query */
        $query = $this->getContainer()->get('dbal_connection')->createQueryBuilder();

        $query->select(['uuid', 'is_default', 'fallback_locale_uuid']);
        $query->from('shop', 'shop');
        $query->where('shop.uuid = :uuid');
        $query->setParameter('uuid', $shopUuid);

        $data = $query->execute()->fetch(\PDO::FETCH_ASSOC);

        return new TranslationContext(
            $data['uuid'],
            (bool) $data['is_default'],
            $data['fallback_locale_uuid'] ?: null
        );
    }
}
