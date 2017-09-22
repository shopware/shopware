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

namespace Shopware\Category\Command;

use Doctrine\DBAL\Connection;
use Shopware\Category\Gateway\CategoryDenormalization;
use Shopware\Category\Struct\CategoryBasicCollection;
use Shopware\Category\Struct\CategoryBasicStruct;
use Shopware\Context\Struct\TranslationContext;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RebuildTreeCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('category:rebuild:tree')
            ->setDescription('Rebuild the category tree')
            ->addOption('offset', 'o', InputOption::VALUE_OPTIONAL, 'Offset to start with.')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Categories to build per batch. Default: 3000');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $progress = $input->getOption('offset') ?: 0;
        $limit = $input->getOption('limit') ?: 3000;

        /** @var CategoryDenormalization $component */
        $component = $this->getContainer()->get('shopware.category.gateway.category_denormalization');

        // Cleanup before the first call
        if ($progress == 0) {
            $output->writeln('Removing orphans');
            $component->removeOrphanedAssignments();
            $output->writeln('Rebuild path info');
            $this->updateCategoryPath();
            $output->writeln('Removing assignments');
            $component->removeAllAssignments();
        }
        // Get total number of assignments to build
        $output->write('Counting…');
        $count = $component->rebuildAllAssignmentsCount();
        $output->writeln("\rCounted {$count} items");

        $progressHelper = new ProgressBar($output, $count);
        $progressHelper->setFormat('verbose');
        $progressHelper->advance($progress);

        // create the assignments
        while ($progress < $count) {
            $component->rebuildAllAssignments($limit, $progress);
            $progress += $limit;
            $progressHelper->advance($limit);
        }
        $progressHelper->finish();
    }

    private function updateCategoryPath(): void
    {
        /** @var Connection $connection */
        $connection = $this->getContainer()->get('dbal_connection');
        $connection->executeUpdate('UPDATE category SET path = NULL');

        $context = new TranslationContext('SWAG-SHOP-UUID-1', true, null);

        $builder = $this->getContainer()->get('shopware.category.denormalization.category_path_builder');
        $builder->update('SWAG-CATEGORY-UUID-1', $context);
    }
}
