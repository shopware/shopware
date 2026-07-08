<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;

/**
 * @phpstan-import-type ResolvedSeoUrlArray from AbstractSeoResolver
 */
#[Package('inventory')]
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
     * @deprecated tag:v6.8.0 - will be removed in v6.8.0, use {@see resolveUrl()} instead
     *
     * @return ResolvedSeoUrlArray
     */
    public function resolve(string $languageId, string $salesChannelId, string $pathInfo): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', self::class . '::resolveUrl()')
        );

        $resolved = $this->resolveUrl(new SeoUrlRequestContext($languageId, $salesChannelId, $pathInfo));

        $data = [
            'pathInfo' => $resolved->pathInfo,
            'isCanonical' => $resolved->isCanonical,
        ];

        if ($resolved->id !== null) {
            $data['id'] = $resolved->id;
        }

        if ($resolved->canonicalPathInfo !== null) {
            $data['canonicalPathInfo'] = $resolved->canonicalPathInfo;
        }

        if ($resolved->seoPathInfo !== null) {
            $data['seoPathInfo'] = $resolved->seoPathInfo;
        }

        return $data;
    }

    public function resolveUrl(SeoUrlRequestContext $context): ResolvedSeoUrl
    {
        $seoPathInfo = trim($context->pathInfo, '/');
        $normalizedQueryString = $this->normalizeQueryString($context->queryString);

        $query = (new QueryBuilder($this->connection))
            ->select('id', 'path_info pathInfo', 'seo_path_info seoPathInfo', 'is_canonical isCanonical', 'sales_channel_id salesChannelId')
            ->from('seo_url')
            ->where('language_id = :language_id')
            ->andWhere('(sales_channel_id = :sales_channel_id OR sales_channel_id IS NULL)')
            ->andWhere('seo_url.is_deleted = 0');

        $seoPathConditions = [
            'seo_path_info = :seoPath',
            'seo_path_info = :seoPathWithSlash',
        ];

        $query->setParameter('language_id', Uuid::fromHexToBytes($context->languageId))
            ->setParameter('sales_channel_id', Uuid::fromHexToBytes($context->salesChannelId))
            ->setParameter('seoPath', $seoPathInfo)
            ->setParameter('seoPathWithSlash', $seoPathInfo . '/');

        $queryCandidates = array_values(array_unique(array_filter(
            [$normalizedQueryString, $context->queryString],
            static fn (?string $query): bool => $query !== null && $query !== ''
        )));

        foreach ($queryCandidates as $index => $candidate) {
            $seoPathConditions[] = "seo_path_info = :seoPathWithQuery{$index}";
            $seoPathConditions[] = "seo_path_info = :seoPathWithSlashAndQuery{$index}";
            $query->setParameter("seoPathWithQuery{$index}", $seoPathInfo . '?' . $candidate)
                ->setParameter("seoPathWithSlashAndQuery{$index}", $seoPathInfo . '/?' . $candidate);
        }

        $query->andWhere('(' . implode(' OR ', $seoPathConditions) . ')');
        $query->setTitle('seo-url::resolve');

        $seoPaths = $query->executeQuery()->fetchAllAssociative();

        usort($seoPaths, function ($a, $b) use ($normalizedQueryString) {
            if ($a['isCanonical'] === null) {
                return 1;
            }

            if ($b['isCanonical'] === null) {
                return -1;
            }

            if ($a['salesChannelId'] === null) {
                return 1;
            }

            if ($b['salesChannelId'] === null) {
                return -1;
            }

            if ($normalizedQueryString !== null) {
                $aMatches = $this->storedQueryMatches($a['seoPathInfo'] ?? null, $normalizedQueryString);
                $bMatches = $this->storedQueryMatches($b['seoPathInfo'] ?? null, $normalizedQueryString);
                if ($aMatches !== $bMatches) {
                    return $aMatches ? -1 : 1;
                }
            }

            return 0;
        });

        $seoPath = ['pathInfo' => $seoPathInfo, 'isCanonical' => false];

        foreach ($seoPaths as $path) {
            $seoPath = $path;
            if ($path['isCanonical']) {
                break;
            }
        }

        if (!$seoPath['isCanonical']) {
            $query = (new QueryBuilder($this->connection))
                ->select('path_info pathInfo', 'seo_path_info seoPathInfo')
                ->from('seo_url')
                ->where('language_id = :language_id')
                ->andWhere('sales_channel_id = :sales_channel_id')
                ->andWhere('path_info = :pathInfo')
                ->andWhere('is_canonical = 1')
                ->andWhere('is_deleted = 0')
                ->setMaxResults(1)
                ->setParameter('language_id', Uuid::fromHexToBytes($context->languageId))
                ->setParameter('sales_channel_id', Uuid::fromHexToBytes($context->salesChannelId))
                ->setParameter('pathInfo', '/' . ltrim((string) $seoPath['pathInfo'], '/'));

            $query->setTitle('seo-url::resolve-fallback');

            // we only have an id when the hit seo url was not a canonical url, save the one filter condition
            if (isset($seoPath['id'])) {
                $query->andWhere('id != :id')
                    ->setParameter('id', $seoPath['id']);
            }

            $canonicalQueryResult = $query->executeQuery()->fetchAssociative();
            if ($canonicalQueryResult) {
                $seoPath['canonicalPathInfo'] = '/' . ltrim((string) $canonicalQueryResult['seoPathInfo'], '/');
            }
        }

        $seoPath['pathInfo'] = '/' . ltrim((string) $seoPath['pathInfo'], '/');

        return new ResolvedSeoUrl(
            pathInfo: $seoPath['pathInfo'],
            isCanonical: (bool) $seoPath['isCanonical'],
            id: $seoPath['id'] ?? null,
            canonicalPathInfo: $seoPath['canonicalPathInfo'] ?? null,
            seoPathInfo: $seoPath['seoPathInfo'] ?? null,
        );
    }

    private function normalizeQueryString(?string $queryString): ?string
    {
        $normalizedQueryString = Request::normalizeQueryString($queryString);

        return $normalizedQueryString === '' ? null : $normalizedQueryString;
    }

    private function storedQueryMatches(mixed $storedSeoPathInfo, string $normalizedQueryString): bool
    {
        if (!\is_string($storedSeoPathInfo)) {
            return false;
        }

        $storedQuery = parse_url($storedSeoPathInfo, \PHP_URL_QUERY);
        if (!\is_string($storedQuery)) {
            return false;
        }

        return $this->normalizeQueryString($storedQuery) === $normalizedQueryString;
    }
}
