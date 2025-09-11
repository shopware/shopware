<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\Command;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[AsCommand(name: 'seo:deduplicate', description: 'List and optionally delete duplicate canonical SEO URLs where seo_path_info repeats for the same route_name + path_info (language-agnostic).')]
class SeoUrlDeduplicateCommand extends Command
{
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('route', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Limit to specific route names (e.g. frontend.detail.page, frontend.navigation.page). If omitted, all routes are processed.', [])
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would change without writing')
            ->addOption('list', null, InputOption::VALUE_NONE, 'List duplicate canonical seo_url rows grouped by route_name + path_info + seo_path_info')
            ->addOption('non-default-only', null, InputOption::VALUE_NONE, 'Only include non-default language entries (LANGUAGE_SYSTEM excluded)')
            ->addOption('soft-delete', null, InputOption::VALUE_NONE, 'Soft delete redundant rows (set is_canonical = NULL, is_deleted = 1)')
            ->addOption('hard-delete', null, InputOption::VALUE_NONE, 'Physically delete redundant rows instead of soft-deleting')
            ->addOption('prefer-default-keeper', null, InputOption::VALUE_NONE, 'Prefer the default-language entry to be kept (if any)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $routes */
        $routes = array_values(array_map(static fn ($v) => (string) $v, (array) $input->getOption('route')));
        $dryRun = (bool) $input->getOption('dry-run');
        $list = (bool) $input->getOption('list');
        $nonDefaultOnly = (bool) $input->getOption('non-default-only');
        $softDelete = (bool) $input->getOption('soft-delete');
        $hardDelete = (bool) $input->getOption('hard-delete');
        $preferDefaultKeeper = (bool) $input->getOption('prefer-default-keeper');

        if ($softDelete && $hardDelete) {
            $output->writeln('<error>Options --soft-delete and --hard-delete are mutually exclusive. Pick one.</error>');

            return self::FAILURE;
        }

        /** @var array<string, mixed> $params */
        $params = [];
        /** @var array<string, mixed> $types */
        $types = [];

        $whereRouteSu = '';
        $whereRouteD = '';
        $whereLangSu = '';

        if ($routes !== []) {
            $whereRouteSu = ' AND su.route_name IN (:routes)';
            $whereRouteD = ' AND route_name IN (:routes)';
            $params['routes'] = $routes;
            $types['routes'] = ArrayParameterType::STRING;
        }

        if ($nonDefaultOnly) {
            $whereLangSu = ' AND su.language_id != :defaultLang';
            $params['defaultLang'] = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        }

        /**
         * @var list<array{
         *     id: string,
         *     languageId: string,
         *     foreignKey: string,
         *     routeName: string,
         *     pathInfo: string,
         *     seoPathInfo: string,
         *     isGlobal: int|string,
         *     createdAt: string,
         * }> $rows
         */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT
                LOWER(HEX(su.id))             AS id,
                LOWER(HEX(su.language_id))    AS languageId,
                LOWER(HEX(su.foreign_key))    AS foreignKey,
                su.route_name                 AS routeName,
                su.path_info                  AS pathInfo,
                su.seo_path_info              AS seoPathInfo,
                (su.sales_channel_id IS NULL) AS isGlobal,
                su.created_at                 AS createdAt
             FROM seo_url su
             JOIN (
               SELECT route_name, path_info, seo_path_info
               FROM seo_url
               WHERE is_canonical = 1' . $whereRouteD . '
               GROUP BY route_name, path_info, seo_path_info
               HAVING COUNT(*) > 1
             ) d
               ON d.route_name = su.route_name
              AND d.path_info = su.path_info
              AND d.seo_path_info = su.seo_path_info
             WHERE su.is_canonical = 1' . $whereRouteSu . $whereLangSu . '
             ORDER BY su.route_name, su.path_info, su.seo_path_info, (su.sales_channel_id IS NULL) DESC, su.created_at ASC, su.language_id',
            $params,
            $types
        );

        /**
         * Group by route_name + path_info + seo_path_info (language-agnostic).
         *
         * @param array{routeName: string, pathInfo: string, seoPathInfo: string} $r
         */
        $groupKey = static fn (array $r): string => \implode('|', [$r['routeName'], $r['pathInfo'], $r['seoPathInfo']]);

        /** @var array<string, list<array{id: string, languageId: string, routeName: string, isGlobal: int|string, createdAt: string, seoPathInfo: string, pathInfo: string}>> $groups */
        $groups = [];
        foreach ($rows as $row) {
            $key = $groupKey($row);
            $groups[$key] ??= [];
            $groups[$key][] = $row;
        }

        /** @var list<string> $toSoftDelete */
        $toSoftDelete = [];
        // Build deletion candidates
        $defaultLangLower = \strtolower(Defaults::LANGUAGE_SYSTEM);
        foreach ($groups as $group) {
            if ($nonDefaultOnly) {
                // In non-default-only mode, every returned row is a duplicate across languages.
                // Delete all of them (leave default-language entries untouched).
                foreach ($group as $row) {
                    $toSoftDelete[] = $row['id'];
                }
                continue;
            }

            if (\count($group) <= 1) {
                continue;
            }

            $keepId = null;
            if ($preferDefaultKeeper) {
                $defaultRows = [];

                foreach ($group as $row) {
                    if ($row['languageId'] === $defaultLangLower) {
                        $defaultRows[] = $row;
                    }
                }

                if ($defaultRows !== []) {
                    $keepDefault = null;
                    foreach ($defaultRows as $r) {
                        if ((int) $r['isGlobal'] === 1) {
                            $keepDefault = $r;
                            break;
                        }
                    }

                    if ($keepDefault === null) {
                        // By ordering, the first default-language row is the oldest among them
                        $keepDefault = $defaultRows[0];
                    }

                    $keepId = $keepDefault['id'];
                }
            }

            if ($keepId === null) {
                foreach ($group as $row) {
                    if ((int) $row['isGlobal'] === 1) {
                        $keepId = $row['id'];
                        break;
                    }
                }
            }

            if ($keepId === null) {
                // Oldest (due to ordering) becomes the representative
                $keepId = $group[0]['id'];
            }

            foreach ($group as $row) {
                if ($row['id'] === $keepId) {
                    continue;
                }

                $toSoftDelete[] = $row['id'];
            }
        }

        $duplicateGroupCount = \count($groups);
        $output->writeln(\sprintf('Duplicate groups found: %d', $duplicateGroupCount));
        $output->writeln(\sprintf('Redundant canonical entries to delete: %d', \count($toSoftDelete)));

        if ($list) {
            $printed = 0;
            foreach ($groups as $group) {
                if (\count($group) <= 1) {
                    continue;
                }

                $first = $group[0];
                $output->writeln(\sprintf(
                    'Duplicate group: lang=%s route=%s path=\'%s\' seo=\'%s\' (rows=%d)',
                    $first['languageId'],
                    $first['routeName'],
                    $first['pathInfo'],
                    $first['seoPathInfo'],
                    \count($group)
                ));

                foreach ($group as $row) {
                    $output->writeln(\sprintf(
                        '  id=%s global=%s createdAt=%s',
                        $row['id'],
                        ((int) $row['isGlobal']) === 1 ? '1' : '0',
                        $row['createdAt']
                    ));
                }
                ++$printed;
            }
            $output->writeln(\sprintf('Duplicate groups listed: %d', $printed));
        }

        if ($list || $dryRun || (!$softDelete && !$hardDelete)) {
            return self::SUCCESS;
        }

        RetryableTransaction::retryable($this->connection, function () use ($hardDelete, $toSoftDelete): void {
            if ($toSoftDelete === []) {
                return;
            }

            if ($hardDelete) {
                $this->connection->executeStatement(
                    'DELETE FROM seo_url WHERE id IN (:ids)',
                    ['ids' => $this->hexListToBytes($toSoftDelete)],
                    ['ids' => ArrayParameterType::BINARY]
                );

                return;
            }

            // Soft delete: remove canonical flag and mark as deleted
            $this->connection->executeStatement(
                'UPDATE seo_url SET is_canonical = NULL, is_deleted = 1 WHERE id IN (:ids)',
                ['ids' => $this->hexListToBytes($toSoftDelete)],
                ['ids' => ArrayParameterType::BINARY]
            );
        });

        return self::SUCCESS;
    }

    /**
     * @param list<string> $hexList
     *
     * @return list<string>
     */
    private function hexListToBytes(array $hexList): array
    {
        // Avoid Uuid dependency here; DB expects raw binary
        return array_map(static function (string $hex): string {
            return Uuid::fromHexToBytes($hex);
        }, $hexList);
    }
}
