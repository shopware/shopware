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

namespace Shopware\Category\Command;

use Doctrine\DBAL\Connection;
use Shopware\Category\Extension\CategoryPathBuilder;
use Shopware\Context\Struct\TranslationContext;
use Shopware\DbalIndexing\Event\ProgressAdvancedEvent;
use Shopware\DbalIndexing\Event\ProgressFinishedEvent;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class BuildCategoryPathCommand extends ContainerAwareCommand implements EventSubscriberInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var CategoryPathBuilder
     */
    private $pathBuilder;

    /**
     * @var SymfonyStyle
     */
    private $io;

    public function __construct(Connection $connection, CategoryPathBuilder $pathBuilder)
    {
        parent::__construct('category:build:path');
        $this->connection = $connection;
        $this->pathBuilder = $pathBuilder;
    }

    public static function getSubscribedEvents()
    {
        return [
            ProgressAdvancedEvent::NAME => 'advanceProgress',
            ProgressFinishedEvent::NAME => 'finishProgress',
        ];
    }

    public function finishProgress(ProgressFinishedEvent $event)
    {
        if (!$this->io) {
            return;
        }
        $this->io->progressFinish();
        $this->io->success($event->getMessage());
    }

    public function advanceProgress(ProgressAdvancedEvent $event)
    {
        if (!$this->io) {
            return;
        }
        $this->io->progressAdvance($event->getStep());
    }


    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('category:build:path')
            ->setDescription('Rebuilds the category path');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
        $context = new TranslationContext('SWAG-SHOP-UUID-1', true, null);

        $this->connection->executeUpdate('UPDATE category SET path = NULL');
        $count = $this->connection->fetchColumn('SELECT COUNT(uuid) FROM category WHERE parent_uuid IS NOT NULL');

        $this->io->writeln('Starting building category paths');
        $this->io->progressStart($count);

        $this->pathBuilder->update('SWAG-CATEGORY-UUID-1', $context);
    }
}
