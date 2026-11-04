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

        $query->setParameter('language_id', Uuid::fromHexToBytes($context->languageId))
            ->setParameter('sales_channel_id', Uuid::fromHexToBytes($context->salesChannelId));

        $pathCandidates = [$seoPathInfo];
        $decodedSeoPathInfo = (string) preg_replace_callback(
            '/(?:%[89A-Fa-f][0-9A-Fa-f])+/',
            static fn (array $match): string => rawurldecode($match[0]),
            $seoPathInfo
        );
        if ($decodedSeoPathInfo !== $seoPathInfo && mb_check_encoding($decodedSeoPathInfo, 'UTF-8')) {
            $pathCandidates[] = $decodedSeoPathInfo;
        }

        $queryCandidates = array_values(array_unique(array_filter(
            [$normalizedQueryString, $context->queryString],
            static fn (?string $query): bool => $query !== null && $query !== ''
        )));

        $literalSeoPaths = $this->buildSeoPathVariants($seoPathInfo, $queryCandidates);

        $seoPathConditions = [];
        foreach ($pathCandidates as $pathIndex => $pathCandidate) {
            foreach ($this->buildSeoPathVariants($pathCandidate, $queryCandidates) as $variantIndex => $variant) {
                $seoPathConditions[] = "seo_path_info = :seoPath{$pathIndex}_{$variantIndex}";
                $query->setParameter("seoPath{$pathIndex}_{$variantIndex}", $variant);
            }
        }

        $query->andWhere('(' . implode(' OR ', $seoPathConditions) . ')');
        $query->setTitle('seo-url::resolve');

        /** @var list<array{id: string, pathInfo: string, seoPathInfo: string, isCanonical: string|null, salesChannelId: string|null}> $seoPaths */
        $seoPaths = $query->executeQuery()->fetchAllAssociative();

        usort($seoPaths, function ($a, $b) use ($normalizedQueryString, $literalSeoPaths) {
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
                $aMatches = $this->storedQueryMatches($a['seoPathInfo'], $normalizedQueryString);
                $bMatches = $this->storedQueryMatches($b['seoPathInfo'], $normalizedQueryString);
                if ($aMatches !== $bMatches) {
                    return $aMatches ? -1 : 1;
                }
            }

            $aIsLiteral = \in_array($a['seoPathInfo'], $literalSeoPaths, true);
            $bIsLiteral = \in_array($b['seoPathInfo'], $literalSeoPaths, true);
            if ($aIsLiteral !== $bIsLiteral) {
                return $aIsLiteral ? -1 : 1;
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
            $canonicalPathInfo = '/' . ltrim((string) $seoPath['pathInfo'], '/');
            $canonicalPathInfoWithQuery = $normalizedQueryString === null
                ? null
                : $canonicalPathInfo . '?' . $normalizedQueryString;

            $query = (new QueryBuilder($this->connection))
                ->select('path_info pathInfo', 'seo_path_info seoPathInfo')
                ->from('seo_url')
                ->where('language_id = :language_id')
                ->andWhere('sales_channel_id = :sales_channel_id')
                ->andWhere($canonicalPathInfoWithQuery === null ? 'path_info = :pathInfo' : 'path_info IN (:pathInfo, :pathInfoWithQuery)')
                ->andWhere('is_canonical = 1')
                ->andWhere('is_deleted = 0')
                ->setMaxResults(1)
                ->setParameter('language_id', Uuid::fromHexToBytes($context->languageId))
                ->setParameter('sales_channel_id', Uuid::fromHexToBytes($context->salesChannelId))
                ->setParameter('pathInfo', $canonicalPathInfo);

            if ($canonicalPathInfoWithQuery !== null) {
                $query->addOrderBy('path_info = :pathInfoWithQuery', 'DESC')
                    ->setParameter('pathInfoWithQuery', $canonicalPathInfoWithQuery);
            }

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

    /**
     * @param list<string> $queryCandidates
     *
     * @return list<string>
     */
    private function buildSeoPathVariants(string $seoPath, array $queryCandidates): array
    {
        $variants = [$seoPath, $seoPath . '/'];
        foreach ($queryCandidates as $candidate) {
            $variants[] = $seoPath . '?' . $candidate;
            $variants[] = $seoPath . '/?' . $candidate;
        }

        return $variants;
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
