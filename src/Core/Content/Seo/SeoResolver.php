<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @phpstan-import-type ResolvedSeoUrl from AbstractSeoResolver
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
     * @return ResolvedSeoUrl
     */
    public function resolve(string $languageId, string $salesChannelId, string $pathInfo): array
    {
        $seoPathInfo = trim($pathInfo, '/');
        $languageIdBytes = Uuid::fromHexToBytes($languageId);
        $systemDefaultLanguageIdBytes = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $languageIds = [$languageIdBytes];
        if ($systemDefaultLanguageIdBytes !== $languageIdBytes) {
            $languageIds[] = $systemDefaultLanguageIdBytes;
        }

        $query = (new QueryBuilder($this->connection))
            ->select('id', 'path_info pathInfo', 'is_canonical isCanonical', 'sales_channel_id salesChannelId', 'language_id languageId')
            ->from('seo_url')
            ->where('language_id IN (:language_ids)')
            ->andWhere('(sales_channel_id = :sales_channel_id OR sales_channel_id IS NULL)')
            ->andWhere('(seo_path_info = :seoPath OR seo_path_info = :seoPathWithSlash)')
            ->setParameter('language_ids', $languageIds, ArrayParameterType::BINARY)
            ->setParameter('sales_channel_id', Uuid::fromHexToBytes($salesChannelId))
            ->setParameter('seoPath', $seoPathInfo)
            ->setParameter('seoPathWithSlash', $seoPathInfo . '/');

        $query->setTitle('seo-url::resolve');

        $seoPaths = $query->executeQuery()->fetchAllAssociative();

        // sort seoPaths by preferred language (current first), then by filled salesChannelId and isCanonical
        usort($seoPaths, static function ($a, $b) use ($languageIdBytes) {
            $aLang = $a['languageId'] ?? null;
            $bLang = $b['languageId'] ?? null;

            $aLangMatch = $aLang === $languageIdBytes ? 1 : 0;
            $bLangMatch = $bLang === $languageIdBytes ? 1 : 0;

            if ($aLangMatch !== $bLangMatch) {
                return $bLangMatch <=> $aLangMatch; // prefer current language
            }

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

            return 0;
        });

        $seoPath = $seoPaths[0] ?? ['pathInfo' => $seoPathInfo, 'isCanonical' => false];

        if (!$seoPath['isCanonical']) {
            $query = (new QueryBuilder($this->connection))
                ->select('id', 'path_info pathInfo', 'seo_path_info seoPathInfo', 'language_id languageId', 'sales_channel_id salesChannelId')
                ->from('seo_url')
                ->where('language_id IN (:language_ids)')
                ->andWhere('(sales_channel_id = :sales_channel_id OR sales_channel_id IS NULL)')
                ->andWhere('path_info = :pathInfo')
                ->andWhere('is_canonical = 1')
                ->setMaxResults(2)
                ->setParameter('language_ids', $languageIds, ArrayParameterType::BINARY)
                ->setParameter('sales_channel_id', Uuid::fromHexToBytes($salesChannelId))
                ->setParameter('pathInfo', '/' . ltrim((string) $seoPath['pathInfo'], '/'));

            $query->setTitle('seo-url::resolve-fallback');

            // we only have an id when the hit seo url was not a canonical url, save the one filter condition
            if (isset($seoPath['id'])) {
                $query->andWhere('id != :id')
                    ->setParameter('id', $seoPath['id']);
            }

            $canonicalResults = $query->executeQuery()->fetchAllAssociative();
            if ($canonicalResults) {
                usort($canonicalResults, static function ($a, $b) use ($languageIdBytes) {
                    $aLangMatch = ($a['languageId'] ?? null) === $languageIdBytes ? 1 : 0;
                    $bLangMatch = ($b['languageId'] ?? null) === $languageIdBytes ? 1 : 0;

                    return $bLangMatch <=> $aLangMatch; // prefer current language
                });

                $chosen = $canonicalResults[0];
                $seoPath['canonicalPathInfo'] = '/' . ltrim((string) $chosen['seoPathInfo'], '/');
            }
        }

        $seoPath['pathInfo'] = '/' . ltrim((string) $seoPath['pathInfo'], '/');

        return $seoPath;
    }
}
