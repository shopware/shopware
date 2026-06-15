<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationEntity;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageTranslation\LandingPageTranslationEntity;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('discovery')]
readonly class EntityCmsSlotConfigInheritanceBuilder
{
    /**
     * @internal
     */
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @template TTranslation of CategoryTranslationEntity|LandingPageTranslationEntity|ProductTranslationEntity
     *
     * @param EntityCollection<TTranslation>|null $translations
     *
     * @return array<string, array<string, mixed>>|null
     */
    public function build(?EntityCollection $translations, SalesChannelContext $context): ?array
    {
        $slotConfigs = $this->collectSlotConfigs($translations);
        $languageInheritanceChain = $this->getLanguageInheritanceChain($context);

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
    private function getLanguageInheritanceChain(SalesChannelContext $context): array
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
        $parentId = $this->connection->createQueryBuilder()
            ->select('LOWER(HEX(language.parent_id))')
            ->from('language', 'language')
            ->where('language.id = :id')
            ->setParameter('id', Uuid::fromHexToBytes($languageId))
            ->executeQuery()
            ->fetchOne();

        if ($parentId === false) {
            return null;
        }

        return $parentId;
    }
}
