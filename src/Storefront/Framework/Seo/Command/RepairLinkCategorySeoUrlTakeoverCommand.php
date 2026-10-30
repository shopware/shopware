<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\Command;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 *
 * @deprecated tag:v6.9.0 - Will be removed
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Storefront\Framework\Seo\Command\RepairLinkCategorySeoUrlTakeoverCommandTest
 */
#[Package('inventory')]
#[AsCommand(
    name: 'seo:repair:link-category-takeover',
    description: 'Lists and repairs categories whose SEO URL was taken over by a link category of the same name',
    help: <<<'HELP'
        Lists the affected categories and changes nothing:

          <info>bin/console seo:repair:link-category-takeover</info>

        A category whose SEO URL was set manually can appear in the list as well.
        Repair asks for each listed category whether it should get its URL back from the link category:

          <info>bin/console seo:repair:link-category-takeover --apply</info>

        To repair without being asked, for example in a script, pass the ids of the categories to repair:

          <info>bin/console seo:repair:link-category-takeover --apply --no-interaction <category-id> <category-id></info>

        Repairing removes the write protection of the fallback URL and regenerates the SEO URLs of the categories,
        so they get their URL back from the link category.
        HELP,
)]
final readonly class RepairLinkCategorySeoUrlTakeoverCommand
{
    public function __construct(
        private Connection $connection,
        private SeoUrlUpdater $seoUrlUpdater,
    ) {
    }

    /**
     * @param list<string> $categoryIds
     */
    public function __invoke(
        InputInterface $input,
        SymfonyStyle $io,
        #[Argument(description: 'Only list or repair these categories')]
        array $categoryIds = [],
        #[Option(description: 'Repair the listed categories')]
        bool $apply = false,
    ): int {
        Feature::triggerDeprecationOrThrow(
            'v6.9.0.0',
            'The seo:repair:link-category-takeover command is deprecated and will be removed in v6.9.0.0.',
            silentUntil: 'v6.8.0.0',
        );

        foreach ($categoryIds as $categoryId) {
            if (!Uuid::isValid($categoryId)) {
                $io->error(\sprintf('"%s" is not a valid category id.', $categoryId));

                return Command::INVALID;
            }
        }

        $affected = $this->findAffected($categoryIds);
        if ($affected === []) {
            $io->success('No category is affected.');

            return Command::SUCCESS;
        }

        $io->table(
            ['Category', 'Name', 'Language', 'Locked URL', 'Created', 'URL held by link category', 'Created'],
            array_map(static fn (array $row): array => [
                $row['category_id'],
                $row['name'],
                $row['language'],
                $row['locked_url'],
                $row['locked_url_created_at'],
                $row['link_url'],
                $row['link_url_created_at'],
            ], $affected),
        );

        if (!$apply) {
            $io->note('Nothing changed. Run the command again with --apply to repair the listed categories.');

            return Command::SUCCESS;
        }

        if ($categoryIds === [] && !$input->isInteractive()) {
            $io->error('Run interactively or pass the ids of the categories to repair.');

            return Command::INVALID;
        }

        if ($categoryIds === []) {
            $affected = $this->askWhichToRepair($io, $affected);
        }

        if ($affected === []) {
            $io->note('Nothing changed.');

            return Command::SUCCESS;
        }

        $affectedCategoryIds = array_values(array_unique(array_column($affected, 'category_id')));

        RetryableQuery::retryable($this->connection, function () use ($affected): void {
            $this->connection->executeStatement(
                'UPDATE seo_url SET is_modified = 0 WHERE id IN (:ids)',
                ['ids' => Uuid::fromHexToBytesList(array_column($affected, 'locked_url_id'))],
                ['ids' => ArrayParameterType::BINARY]
            );
        });

        $this->seoUrlUpdater->update(NavigationPageSeoUrlRoute::ROUTE_NAME, $affectedCategoryIds);

        $io->success(\sprintf('Repaired %d categories.', \count($affectedCategoryIds)));

        return Command::SUCCESS;
    }

    /**
     * @param list<array{category_id: string, name: string, language: string, locked_url_id: string, locked_url: string, locked_url_created_at: string, link_url: string, link_url_created_at: string}> $affected
     *
     * @return list<array{category_id: string, name: string, language: string, locked_url_id: string, locked_url: string, locked_url_created_at: string, link_url: string, link_url_created_at: string}>
     */
    private function askWhichToRepair(SymfonyStyle $io, array $affected): array
    {
        $selected = [];
        foreach (array_unique(array_column($affected, 'category_id')) as $categoryId) {
            $rows = array_values(array_filter($affected, static fn (array $row): bool => $row['category_id'] === $categoryId));

            $question = \sprintf(
                'Give "%s" back to "%s" (%s) instead of "%s"?',
                implode('", "', array_unique(array_column($rows, 'link_url'))),
                $rows[0]['name'],
                $categoryId,
                implode('", "', array_unique(array_column($rows, 'locked_url'))),
            );

            if ($io->confirm($question, false)) {
                array_push($selected, ...$rows);
            }
        }

        return $selected;
    }

    /**
     * @param list<string> $categoryIds
     *
     * @return list<array{category_id: string, name: string, language: string, locked_url_id: string, locked_url: string, locked_url_created_at: string, link_url: string, link_url_created_at: string}>
     */
    private function findAffected(array $categoryIds): array
    {
        // like the DAL, fall back to the system language translation
        $query = $this->connection->createQueryBuilder()
            ->select(
                'LOWER(HEX(target.id)) AS category_id',
                'COALESCE(target_translation.name, target_default.name) AS name',
                'language.name AS language',
                'LOWER(HEX(target_url.id)) AS locked_url_id',
                'target_url.seo_path_info AS locked_url',
                'target_url.created_at AS locked_url_created_at',
                'link_url.seo_path_info AS link_url',
                'link_url.created_at AS link_url_created_at',
            )
            ->from('seo_url', 'link_url')
            ->innerJoin('link_url', 'language', 'language', 'language.id = link_url.language_id')
            ->innerJoin('link_url', 'category', 'link', 'link.id = link_url.foreign_key AND link.version_id = :versionId AND link.type = :linkType')
            ->leftJoin('link', 'category_translation', 'link_translation', 'link_translation.category_id = link.id AND link_translation.category_version_id = link.version_id AND link_translation.language_id = link_url.language_id')
            ->innerJoin('link', 'category_translation', 'link_default', 'link_default.category_id = link.id AND link_default.category_version_id = link.version_id AND link_default.language_id = :systemLanguageId')
            ->innerJoin('link', 'category', 'target', 'target.id = COALESCE(link_translation.internal_link, link_default.internal_link) AND target.version_id = link.version_id')
            ->leftJoin('target', 'category_translation', 'target_translation', 'target_translation.category_id = target.id AND target_translation.category_version_id = target.version_id AND target_translation.language_id = link_url.language_id')
            ->innerJoin('target', 'category_translation', 'target_default', 'target_default.category_id = target.id AND target_default.category_version_id = target.version_id AND target_default.language_id = :systemLanguageId')
            ->innerJoin('target', 'seo_url', 'target_url', 'target_url.foreign_key = target.id AND target_url.language_id = link_url.language_id AND target_url.sales_channel_id = link_url.sales_channel_id AND target_url.route_name = link_url.route_name AND target_url.is_canonical = 1 AND target_url.is_modified = 1')
            ->where('link_url.route_name = :routeName AND link_url.is_canonical = 1 AND link_url.is_deleted = 0 AND link_url.is_modified = 0')
            ->andWhere('COALESCE(link_translation.link_type, link_default.link_type) = :linkTypeCategory')
            ->andWhere('COALESCE(link_translation.name, link_default.name) = COALESCE(target_translation.name, target_default.name)')
            ->orderBy('name')
            ->addOrderBy('language')
            ->setParameter('routeName', NavigationPageSeoUrlRoute::ROUTE_NAME)
            ->setParameter('linkType', CategoryDefinition::TYPE_LINK)
            ->setParameter('linkTypeCategory', CategoryDefinition::LINK_TYPE_CATEGORY)
            ->setParameter('versionId', Uuid::fromHexToBytes(Defaults::LIVE_VERSION))
            ->setParameter('systemLanguageId', Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM));

        if ($categoryIds !== []) {
            $query->andWhere('target.id IN (:categoryIds)')
                ->setParameter('categoryIds', Uuid::fromHexToBytesList($categoryIds), ArrayParameterType::BINARY);
        }

        /** @var list<array{category_id: string, name: string, language: string, locked_url_id: string, locked_url: string, locked_url_created_at: string, link_url: string, link_url_created_at: string}> $affected */
        $affected = $query->executeQuery()->fetchAllAssociative();

        return $affected;
    }
}
