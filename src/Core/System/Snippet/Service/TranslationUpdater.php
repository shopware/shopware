<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\DataTransfer\Metadata\MetadataCollection;
use Shopware\Core\System\Snippet\DataTransfer\TranslationUpdate\TranslationUpdateResult;

/**
 * @internal
 */
#[Package('discovery')]
readonly class TranslationUpdater
{
    public function __construct(
        private AbstractTranslationLoader $translationLoader,
        private TranslationMetadataStore $metadataStore,
    ) {
    }

    /**
     * Downloads and persists the translations for every locale in the given collection that is flagged as requiring
     * an update, then stores the collection as the new local metadata.
     */
    public function update(MetadataCollection $metadata, Context $context, bool $activate = true): TranslationUpdateResult
    {
        $localesRequiringUpdate = $metadata->getLocalesRequiringUpdate();
        $skipped = array_values(array_diff($metadata->getKeys(), $localesRequiringUpdate));

        // Nothing changed against the remote, so the on-disk metadata is already current and re-persisting it would be a redundant write.
        if ($localesRequiringUpdate === []) {
            return new TranslationUpdateResult([], $skipped);
        }

        foreach ($localesRequiringUpdate as $locale) {
            $this->translationLoader->load($locale, $context, $activate);
        }

        $this->metadataStore->save($metadata);

        return new TranslationUpdateResult($localesRequiringUpdate, $skipped);
    }

    /**
     * Refreshes currently installed translations against the latest remote metadata.
     * Shops without any installed translation are a no-op and do not trigger a remote request.
     * The scheduled task restricts this to locales of languages with `translationAutoUpdate` enabled.
     *
     * @param list<string>|null $locales Restrict to these (installed) locales; null refreshes all installed translations.
     */
    public function updateInstalled(Context $context, ?array $locales = null): TranslationUpdateResult
    {
        $installedLocales = $this->metadataStore->getLocalMetadata()->getKeys();

        if ($installedLocales === []) {
            return new TranslationUpdateResult();
        }

        if ($locales !== null) {
            $locales = array_values(array_intersect($locales, $installedLocales));

            if ($locales === []) {
                return new TranslationUpdateResult();
            }
        }

        return $this->update($this->metadataStore->getUpdatedLocalMetadata($locales), $context);
    }
}
