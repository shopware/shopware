<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency;

use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;

class CurrencyFormatter
{
    /**
     * @var \NumberFormatter[]
     */
    private array $formatter = [];

    private LanguageLocaleCodeProvider $languageLocaleProvider;

    public function __construct(LanguageLocaleCodeProvider $languageLocaleProvider)
    {
        $this->languageLocaleProvider = $languageLocaleProvider;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws LanguageNotFoundException
     */
    public function formatCurrencyByLanguage(float $price, string $currency, string $languageId, Context $context, ?int $decimals = null): string
    {
        $decimals = $decimals ?? $context->getRounding()->getDecimals();

        $locale = $this->languageLocaleProvider->getLocaleForLanguageId($languageId);
        $formatter = $this->getFormatter($locale, \NumberFormatter::CURRENCY);
        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $decimals);

        if (Feature::isActive('FEATURE_NEXT_15053')) {
            return $formatter->formatCurrency($price, $currency);
        }

        if (!$context->hasState(DocumentService::GENERATING_PDF_STATE)) {
            return $formatter->formatCurrency($price, $currency);
        }

        $string = htmlentities($formatter->formatCurrency($price, $currency), \ENT_COMPAT, 'utf-8');
        $content = str_replace('&nbsp;', ' ', $string);

        return html_entity_decode($content);
    }

    private function getFormatter(string $locale, int $format): \NumberFormatter
    {
        $hash = md5(json_encode([$locale, $format]));

        if (isset($this->formatter[$hash])) {
            return $this->formatter[$hash];
        }

        return $this->formatter[$hash] = new \NumberFormatter($locale, $format);
    }
}
