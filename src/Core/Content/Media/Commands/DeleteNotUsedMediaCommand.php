<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Commands;

use Shopware\Core\Content\Media\DeleteNotUsedMediaService;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteNotUsedMediaCommand extends Command
{
    protected static $defaultName = 'media:delete-unused';

    /**
     * @var DeleteNotUsedMediaService
     */
    private $deleteMediaService;

    public function __construct(DeleteNotUsedMediaService $deleteMediaService)
    {
        parent::__construct();

        $this->deleteMediaService = $deleteMediaService;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Deletes all media files that are never used');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        $context = Context::createDefaultContext();

        $count = $this->deleteMediaService->countNotUsedMedia($context);

        if ($count === 0) {
            $io->comment('No unused media files found.');

            return self::SUCCESS;
        }

        $confirm = $io->confirm(sprintf('Are you sure that you want to delete %d media files?', $count), false);

        if (!$confirm) {
            $io->caution('Aborting due to user input.');

            return self::SUCCESS;
        }

        $this->deleteMediaService->deleteNotUsedMedia($context);
        $io->success(sprintf('Successfully deleted %d media files.', $count));

        return self::SUCCESS;
    }
}
