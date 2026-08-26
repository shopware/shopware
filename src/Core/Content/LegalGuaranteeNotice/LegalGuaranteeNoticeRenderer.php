<?php declare(strict_types=1);

namespace Shopware\Core\Content\LegalGuaranteeNotice;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Twig\Environment;

/**
 * @internal
 */
#[Package('inventory')]
class LegalGuaranteeNoticeRenderer
{
    private const TEMPLATE = '@Content/legal-guarantee-notice/%s.svg';

    private const DEFAULT_LOCALE = 'en';

    private const LINK_BASE_URL = 'https://europa.eu/youreurope/';

    /**
     * "Your Europe" guarantees page slug per locale, published by the European Commission.
     */
    private const LOCALE_LINK_SLUGS = [
        'bg' => 'гаранции',
        'cs' => 'záruky_cs',
        'da' => 'garantier',
        'de' => 'garantien',
        'el' => 'εγγυήσεις',
        'en' => 'guarantees',
        'es' => 'garantías',
        'et' => 'garantiid',
        'fi' => 'virhevastuu',
        'fr' => 'garanties',
        'ga' => 'ráthaíochtaí',
        'hr' => 'jamstva_hr',
        'hu' => 'jótállás',
        'it' => 'garanzie',
        'lt' => 'garantijos',
        'lv' => 'garantijas',
        'mt' => 'garanziji',
        'nl' => 'garantie',
        'pl' => 'gwarancje',
        'pt' => 'garantias',
        'ro' => 'garanții',
        'sk' => 'záruky_sk',
        'sl' => 'jamstva_sl',
        'sv' => 'reklamationsrätt',
    ];

    public function __construct(
        private readonly Environment $twig,
        private readonly LanguageLocaleCodeProvider $localeCodeProvider,
    ) {
    }

    public function renderForLanguage(string $languageId): string
    {
        return $this->twig->render(\sprintf(self::TEMPLATE, $this->resolveLocale($languageId)));
    }

    public function linkForLanguage(string $languageId): string
    {
        $locale = $this->resolveLocale($languageId);

        return self::LINK_BASE_URL . rawurlencode(self::LOCALE_LINK_SLUGS[$locale]);
    }

    private function resolveLocale(string $languageId): string
    {
        $localePrefix = $this->localeCodeProvider->getLanguageLocalePrefix($languageId);

        return \array_key_exists($localePrefix, self::LOCALE_LINK_SLUGS) ? $localePrefix : self::DEFAULT_LOCALE;
    }
}
