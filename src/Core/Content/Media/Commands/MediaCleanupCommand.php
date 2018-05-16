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

namespace Shopware\Content\Media\Commands;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\ORMException;
use Shopware\Components\Model\ModelManager;
use Shopware\Content\Media\Util\GarbageCollector\GarbageCollector;
use Shopware\Models\Media\Media;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MediaCleanupCommand extends Command
{
    /**
     * @var \Shopware\Content\Media\Util\GarbageCollector\GarbageCollector
     */
    private $garbageCollector;

    /**
     * @param \Shopware\Content\Media\Util\GarbageCollector\GarbageCollector $garbageCollector
     */
    public function __construct(GarbageCollector $garbageCollector)
    {
        parent::__construct();

        $this->garbageCollector = $garbageCollector;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('media:cleanup')
            ->setHelp('The <info>%command.name%</info> collects unused media and moves them to the recycle bin album.')
            ->setDescription('Collect unused media move them to trash.')
            ->addOption('delete', false, InputOption::VALUE_NONE, 'Delete unused media.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $io->section('Searching for unused media files.');
        $total = $this->handleMove();
        $io->text(sprintf('%s unused item(s) found.', $total));

        if ($total === 0) {
            return;
        }

        if ($input->getOption('delete')) {
            if ($input->isInteractive() && !$io->confirm('Are you sure you want to delete every item in the recycle bin?')) {
                return;
            }

            $deleted = $this->handleCleanup($io);
            $io->success(sprintf('%d item(s) deleted.', $deleted));

            return;
        }

        $io->success(sprintf('%d item(s) in recycle bin.', $total));
    }

    /**
     * Handles cleaning process and returns the number of deleted media objects
     *
     * @param SymfonyStyle $io
     *
     * @return int
     */
    private function handleCleanup(SymfonyStyle $io): int
    {
        /** @var ModelManager $modelManager */
        $modelManager = $this->getContainer()->get('models');

        /** @var \Shopware\Models\Media\Repository $repository */
        $repository = $modelManager->getRepository(Media::class);

        $query = $repository->getAlbumMediaQuery(-13);
        $query->setHydrationMode(AbstractQuery::HYDRATE_OBJECT);

        $count = $modelManager->getQueryCount($query);
        $iterableResult = $query->iterate();

        $progressBar = $io->createProgressBar($count);

        try {
            foreach ($iterableResult as $key => $row) {
                $media = $row[0];
                $modelManager->remove($media);
                if ($key % 100 === 0) {
                    $modelManager->flush();
                    $modelManager->clear();
                }
                $progressBar->advance();
            }
            $modelManager->flush();
            $modelManager->clear();
        } catch (ORMException $e) {
            $count = 0;
        }

        $progressBar->finish();
        $io->newLine(2);

        return $count;
    }

    private function handleMove(): int
    {
        $this->garbageCollector->run();

        return (int) $this->garbageCollector->getCount();
    }
}
