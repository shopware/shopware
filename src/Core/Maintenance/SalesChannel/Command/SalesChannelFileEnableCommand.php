<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\SalesChannel\Command;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelFile\SalesChannelFileCollection;
use Shopware\Core\System\SalesChannel\File\Discovery\SalesChannelFile;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal should be used over the CLI only
 */
#[Package('discovery')]
#[AsCommand(
    name: 'sales-channel:file:enable',
    description: 'Enable llms.txt and AGENTS.md for Storefront and Headless sales channels',
)]
class SalesChannelFileEnableCommand extends Command
{
    private const FILE_NAMES = ['llms.txt', 'AGENTS.md'];

    /**
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     * @param EntityRepository<SalesChannelFileCollection> $salesChannelFileRepository
     */
    public function __construct(
        private readonly EntityRepository $salesChannelRepository,
        private readonly EntityRepository $salesChannelFileRepository,
    ) {
        parent::__construct();
    }

    /**
     * @param list<string> $ids
     */
    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'Which Sales Channels do you want to enable the files for? (Optional when --all flag is used)')]
        array $ids = [],
        #[Option(description: 'Enable the files for all Storefront and Headless sales channels', shortcut: 'a')]
        bool $all = false,
    ): int {
        if ($all === false && $ids === []) {
            $output->write('No files were enabled. Provide id(s) or run with --all option.');

            return self::SUCCESS;
        }

        $context = Context::createCLIContext();
        $requestedIds = $all ? null : $this->validIds($ids, $output);
        $salesChannelIds = $this->findSalesChannelIds($requestedIds, $context);

        $unmatchedIds = array_values(array_diff($requestedIds ?? [], $salesChannelIds));
        if ($unmatchedIds !== []) {
            $output->writeln(\sprintf('Ignored id(s) that match no Storefront or Headless sales channel: %s', implode(', ', $unmatchedIds)));
        }

        if ($salesChannelIds === []) {
            $output->write('No files were enabled. No Storefront or Headless sales channel matches.');

            return self::SUCCESS;
        }

        $missingFiles = $this->missingFiles($salesChannelIds, $context);

        if ($missingFiles === []) {
            $output->write('No files were enabled. The files already exist for every selected sales channel.');

            return self::SUCCESS;
        }

        $this->salesChannelFileRepository->create($missingFiles, $context);

        $output->write(\sprintf(
            'Enabled %d file(s) for %d sales channel(s)',
            \count($missingFiles),
            \count(array_unique(array_column($missingFiles, 'salesChannelId'))),
        ));

        return self::SUCCESS;
    }

    /**
     * The DAL only matches lowercase ids, and an invalid id would abort the search with an exception.
     *
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function validIds(array $ids, OutputInterface $output): array
    {
        $validIds = [];
        $invalidIds = [];
        foreach ($ids as $id) {
            $id = strtolower($id);

            if (Uuid::isValid($id)) {
                $validIds[$id] = $id;

                continue;
            }

            $invalidIds[$id] = $id;
        }

        if ($invalidIds !== []) {
            $output->writeln(\sprintf('Ignored invalid id(s): %s', implode(', ', $invalidIds)));
        }

        return array_values($validIds);
    }

    /**
     * @param list<string>|null $ids
     *
     * @return list<string>
     */
    private function findSalesChannelIds(?array $ids, Context $context): array
    {
        // An empty id list would not restrict the criteria and select every sales channel.
        if ($ids === []) {
            return [];
        }

        $criteria = new Criteria($ids);
        $criteria->addFilter(new EqualsAnyFilter('typeId', [
            Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            Defaults::SALES_CHANNEL_TYPE_API,
        ]));

        return array_values(array_filter(
            $this->salesChannelRepository->searchIds($criteria, $context)->getIds(),
            \is_string(...),
        ));
    }

    /**
     * Existing rows are left untouched, so a file the merchant switched off stays off.
     *
     * @param list<string> $salesChannelIds
     *
     * @return list<array{salesChannelId: string, fileFamily: string, fileName: string, enabled: true}>
     */
    private function missingFiles(array $salesChannelIds, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsAnyFilter('salesChannelId', $salesChannelIds),
            new EqualsFilter('fileFamily', SalesChannelFile::DEFAULT_FILE_FAMILY),
        );

        $existing = [];
        foreach ($this->salesChannelFileRepository->search($criteria, $context)->getEntities() as $file) {
            // File names are matched case-insensitively, so a legacy `agents.md` row counts as `AGENTS.md`.
            $existing[$file->getSalesChannelId()][mb_strtolower($file->getFileName())] = true;
        }

        $missingFiles = [];
        foreach ($salesChannelIds as $salesChannelId) {
            foreach (self::FILE_NAMES as $fileName) {
                if (isset($existing[$salesChannelId][mb_strtolower($fileName)])) {
                    continue;
                }

                $missingFiles[] = [
                    'salesChannelId' => $salesChannelId,
                    'fileFamily' => SalesChannelFile::DEFAULT_FILE_FAMILY,
                    'fileName' => $fileName,
                    'enabled' => true,
                ];
            }
        }

        return $missingFiles;
    }
}
