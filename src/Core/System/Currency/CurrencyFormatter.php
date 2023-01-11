<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;

/**
 * @package inventory
 */
class CurrencyFormatter
{
    /**
     * @var \NumberFormatter[]
     */
    private array $formatter = [];

    private LanguageLocaleCodeProvider $languageLocaleProvider;

    /**
     * @internal
     */
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

        return (string) $formatter->formatCurrency($price, $currency);
    }

    private function getFormatter(string $locale, int $format): \NumberFormatter
    {
        $hash = md5(json_encode([$locale, $format], \JSON_THROW_ON_ERROR));

        if (isset($this->formatter[$hash])) {
            return $this->formatter[$hash];
        }

        return $this->formatter[$hash] = new \NumberFormatter($locale, $format);
    }
}
