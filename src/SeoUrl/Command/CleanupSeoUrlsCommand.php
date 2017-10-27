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

namespace Shopware\SeoUrl\Command;

use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\Query\TermQuery;
use Shopware\Context\Struct\TranslationContext;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CleanupSeoUrlsCommand extends ContainerAwareCommand
{
    protected function configure(): void
    {
        $this
            ->setName('seo:url:cleanup')
            ->setDescription('Deletes all none canonical seo urls')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $repo = $this->getContainer()->get('shopware.seo_url.repository');

        $criteria = new Criteria();
        $criteria->setOffset(0);
        $criteria->setLimit(100);
        $criteria->addFilter(new TermQuery('is_canonical', false));

        $context = new TranslationContext('SWAG-SHOP-UUID-1', true, null);

        $ids = $repo->search($criteria, $context)->getUuids();

        do {
            $repo->delete($ids);

            $criteria->setOffset($criteria->getOffset() + $criteria->getLimit());

            $ids = $repo->search($criteria, $context)->getUuids();
        } while ($ids);
    }
}
