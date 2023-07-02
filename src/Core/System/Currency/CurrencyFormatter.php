<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;

#[Package('inventory')]
class CurrencyFormatter
{
    /**
     * @var \NumberFormatter[]
     */
    private array $formatter = [];

    /**
     * @internal
     */
    public function __construct(private readonly LanguageLocaleCodeProvider $languageLocaleProvider)
    {
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws LanguageNotFoundException
     */
    public function formatCurrencyByLanguage(float $price, string $currency, string $languageId, Context $context, ?int $decimals = null): string
    {
        $decimals ??= $context->getRounding()->getDecimals();

        $formatter = $this->getFormatter(
            $this->languageLocaleProvider->getLocaleForLanguageId($languageId)
        );
        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $decimals);

        return (string) $formatter->formatCurrency($price, $currency);
    }

    private function getFormatter(string $locale): \NumberFormatter
    {
        if (isset($this->formatter[$locale])) {
            return $this->formatter[$locale];
        }

        return $this->formatter[$locale] = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
    }
}
