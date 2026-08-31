<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\DataTransfer\Metadata\MetadataCollection;
use Shopware\Core\System\Snippet\DataTransfer\TranslationUpdate\TranslationInstallPlan;
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
     * Splits the requested locales into the work an install has to do. Whether a translation is up to date and whether
     * it is actually installed are two different questions: files are only fetched when the repository has something
     * newer or when they are missing locally, while the language and snippet set are ensured either way.
     *
     * @param list<string> $locales
     */
    public function planInstall(array $locales, MetadataCollection $metadata): TranslationInstallPlan
    {
        $localesRequiringUpdate = $metadata->getLocalesRequiringUpdate();
        $notRequiringUpdate = array_values(array_diff($locales, $localesRequiringUpdate));

        $localesToLink = array_values(array_filter(
            $notRequiringUpdate,
            fn (string $locale) => $this->translationLoader->hasTranslationFiles($locale),
        ));

        $known = $metadata->getKeys();

        return new TranslationInstallPlan(
            localesToDownload: array_values(array_merge(
                $localesRequiringUpdate,
                array_intersect(array_diff($notRequiringUpdate, $localesToLink), $known),
            )),
            localesToLink: $localesToLink,
            unavailableLocales: array_values(array_diff($locales, $known, $localesToLink)),
        );
    }

    /**
     * Ensures the language and snippet set for every locale of the plan. Unlike update() this never returns early when
     * nothing requires an update, because a locale whose files are current may still have no language behind it.
     * Downloading and creating are separate steps on purpose: link() refuses a locale whose download produced no file,
     * so a repository that offers nothing for a locale cannot leave a language without translations behind.
     *
     * Persisting the metadata is left to the caller, which decides how a failing write is reported.
     *
     * @param callable(string): void|null $onLocale receives each locale before it is installed, for progress output
     */
    public function install(
        TranslationInstallPlan $plan,
        MetadataCollection $metadata,
        Context $context,
        bool $activate = true,
        ?callable $onLocale = null,
    ): TranslationUpdateResult {
        $onLocale ??= static function (): void {
        };

        foreach ($plan->localesToDownload as $locale) {
            $onLocale($locale);

            $this->translationLoader->download($locale);
            $this->translationLoader->link($locale, $context, $activate);
        }

        foreach ($plan->localesToLink as $locale) {
            $onLocale($locale);

            $this->translationLoader->link($locale, $context, $activate);
        }

        return new TranslationUpdateResult(
            $plan->localesToDownload,
            array_values(array_diff($metadata->getKeys(), $plan->localesToDownload)),
        );
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
