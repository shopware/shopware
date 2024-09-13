<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Commands;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'media:delete-local-thumbnails',
    description: 'Deletes all physical media thumbnails when remote thumbnails is enabled.',
)]
#[Package('buyers-experience')]
class DeleteThumbnailsCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $thumbnailRepository,
        private readonly bool $remoteThumbnailsEnable = false
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        if (!$this->remoteThumbnailsEnable) {
            $io->comment('Deleting thumbnails is only supported when remote thumbnail is enabled.');

            return self::FAILURE;
        }

        $this->deleteThumbnails();

        $io->success('Successfully deleted all thumbnails records and thumbnails files.');

        return self::SUCCESS;
    }

    private function deleteThumbnails(): void
    {
        $thumbnailIds = $this->connection->fetchAllAssociative('SELECT LOWER(HEX(`id`)) as id FROM `media_thumbnail`');

        $this->thumbnailRepository->delete($thumbnailIds, Context::createCLIContext());

        $this->connection->executeStatement('UPDATE `media` SET `thumbnails_ro` = NULL;');
    }
}
