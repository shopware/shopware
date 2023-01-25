<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @phpstan-import-type ResolvedSeoUrl from AbstractSeoResolver
 */
#[Package('sales-channel')]
class SeoResolver extends AbstractSeoResolver
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public function getDecorated(): AbstractSeoResolver
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @return ResolvedSeoUrl
     */
    public function resolve(string $languageId, string $salesChannelId, string $pathInfo): array
    {
        $seoPathInfo = ltrim($pathInfo, '/');

        $query = (new QueryBuilder($this->connection))
            ->select('id', 'path_info pathInfo', 'is_canonical isCanonical')
            ->from('seo_url')
            ->where('language_id = :language_id')
            ->andWhere('(sales_channel_id = :sales_channel_id OR sales_channel_id IS NULL)')
            ->andWhere('seo_path_info = :seoPath')
            ->orderBy('seo_path_info')
            ->addOrderBy('sales_channel_id IS NULL') // sales_channel_specific comes first
            ->setMaxResults(1)
            ->setParameter('language_id', Uuid::fromHexToBytes($languageId))
            ->setParameter('sales_channel_id', Uuid::fromHexToBytes($salesChannelId))
            ->setParameter('seoPath', $seoPathInfo);

        $query->setTitle('seo-url::resolve');

        $seoPath = $query->executeQuery()->fetchAssociative();

        $seoPath = $seoPath !== false
            ? $seoPath
            : ['pathInfo' => $seoPathInfo, 'isCanonical' => false];

        if (!$seoPath['isCanonical']) {
            $query = $this->connection->createQueryBuilder()
                ->select('path_info pathInfo', 'seo_path_info seoPathInfo')
                ->from('seo_url')
                ->where('language_id = :language_id')
                ->andWhere('sales_channel_id = :sales_channel_id')
                ->andWhere('id != :id')
                ->andWhere('path_info = :pathInfo')
                ->andWhere('is_canonical = 1')
                ->setMaxResults(1)
                ->setParameter('language_id', Uuid::fromHexToBytes($languageId))
                ->setParameter('sales_channel_id', Uuid::fromHexToBytes($salesChannelId))
                ->setParameter('id', $seoPath['id'] ?? '')
                ->setParameter('pathInfo', '/' . ltrim((string) $seoPath['pathInfo'], '/'));

            $canonical = $query->executeQuery()->fetchAssociative();
            if ($canonical) {
                $seoPath['canonicalPathInfo'] = '/' . ltrim((string) $canonical['seoPathInfo'], '/');
            }
        }

        $seoPath['pathInfo'] = '/' . ltrim((string) $seoPath['pathInfo'], '/');

        return $seoPath;
    }
}
