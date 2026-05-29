<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationEntity;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageTranslation\LandingPageTranslationEntity;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\Cache\CacheInterface;

#[Package('discovery')]
readonly class EntityCmsSlotConfigInheritanceBuilder
{
    /**
     * @internal
     */
    public function __construct(
        private Connection $connection,
        private CacheInterface $cache,
    ) {
    }

    /**
     * @template TTranslation of CategoryTranslationEntity|LandingPageTranslationEntity|ProductTranslationEntity
     *
     * @param EntityCollection<TTranslation>|null $translations
     *
     * @return array<string, array<string, mixed>>|null
     */
    public function build(?EntityCollection $translations, SalesChannelContext|Context $context): ?array
    {
        $slotConfigs = $this->collectSlotConfigs($translations);
        $languageInheritanceChain = $this->getLanguageInheritanceChain(
            $context instanceof Context ? $context : $context->getContext()
        );

        /**
         * Merge field-by-field within each slot so that partial slot overrides in the
         * child language do not drop fields that are still inherited from the parent
         * language. Later entries in the chain (child) win over earlier ones (parent).
         */
        $merged = [];
        foreach ($languageInheritanceChain as $currentLanguageId) {
            foreach ($slotConfigs[$currentLanguageId] ?? [] as $slotId => $fields) {
                $merged[$slotId] = \array_replace($merged[$slotId] ?? [], $fields);
            }
        }

        return $merged !== [] ? $merged : null;
    }

    /**
     * @template TTranslation of CategoryTranslationEntity|LandingPageTranslationEntity|ProductTranslationEntity
     *
     * @param EntityCollection<TTranslation>|null $translations
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function collectSlotConfigs(?EntityCollection $translations): array
    {
        if ($translations === null) {
            return [];
        }

        $slotConfigs = [];
        foreach ($translations as $translation) {
            $slotConfig = $translation->getSlotConfig();
            if ($slotConfig === null) {
                continue;
            }

            $slotConfigs[$translation->getLanguageId()] = $slotConfig;
        }

        return $slotConfigs;
    }

    /**
     * @return non-empty-list<string>
     */
    private function getLanguageInheritanceChain(Context $context): array
    {
        $languageId = $context->getLanguageId();

        return [
            ...$this->getParentLanguageInheritanceChain($languageId),
            $languageId,
        ];
    }

    /**
     * @return list<string>
     */
    private function getParentLanguageInheritanceChain(string $languageId): array
    {
        $parentLanguageId = $this->getParentLanguageId($languageId);

        if ($parentLanguageId === null) {
            return [];
        }

        return [
            ...$this->getParentLanguageInheritanceChain($parentLanguageId),
            $parentLanguageId,
        ];
    }

    private function getParentLanguageId(string $languageId): ?string
    {
        return $this->cache->get(
            'language_parent_id_' . $languageId,
            fn () => $this->connection->createQueryBuilder()
                    ->select('LOWER(HEX(language.parent_id))')
                    ->from('language', 'language')
                    ->where('language.id = :id')
                    ->setParameter('id', Uuid::fromHexToBytes($languageId))
                    ->executeQuery()
                    ->fetchOne() ?: null
        );
    }
}
