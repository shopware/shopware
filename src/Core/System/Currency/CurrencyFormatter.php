<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Shopware\Core\System\Language\LanguageEntity;

class CurrencyFormatter
{
    /**
     * @var EntityRepositoryInterface
     */
    protected $languageRepository;

    /**
     * @var string[]
     */
    protected $localeCache = [];

    /**
     * @var \NumberFormatter[]
     */
    private $formatter = [];

    public function __construct(EntityRepositoryInterface $languageRepository)
    {
        $this->languageRepository = $languageRepository;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws LanguageNotFoundException
     */
    public function formatCurrencyByLanguage(float $price, string $currency, string $languageId, Context $context): string
    {
        return $this->formatCurrency(
            $price,
            $this->getLocale($languageId, $context),
            $currency,
            \NumberFormatter::CURRENCY,
            null,
            $context->getCurrencyPrecision()
        );
    }

    /**
     * @deprecated tag:v6.4.0 - Will be removed, use `formatCurrencyByLanguage` instead
     */
    public function formatCurrency(
        float $price,
        string $locale,
        string $currency,
        int $format = \NumberFormatter::CURRENCY,
        ?string $pattern = null,
        ?int $digits = null
    ): ?string {
        $formatter = $this->getFormatter($locale, $format, $pattern);

        if ($digits !== null) {
            $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $digits);
        }

        return $formatter->formatCurrency($price, $currency);
    }

    private function getFormatter(string $locale, int $format, ?string $pattern, ?int $digits = null): \NumberFormatter
    {
        // @deprecated tag:v6.4.0 - As soon as only the function 'formatCurrencyByLanguage' is left we can minimize the internal caches. Here we can remove the pattern and digits from the hash.
        $hash = md5(json_encode([$locale, $format, $pattern, $digits]));

        if (isset($this->formatter[$hash])) {
            return $this->formatter[$hash];
        }

        if ($pattern === null) {
            return $this->formatter[$hash] = new \NumberFormatter($locale, $format);
        }

        return $this->formatter[$hash] = new \NumberFormatter($locale, $format, $pattern);
    }

    private function getLocale(string $languageId, Context $context): string
    {
        if (array_key_exists($languageId, $this->localeCache)) {
            return $this->localeCache[$languageId];
        }

        $criteria = (new Criteria())
            ->addAssociation('locale')
            ->addFilter(new EqualsFilter('language.id', $languageId));

        $criteria->setTitle('currency-formatter::load-locale');

        /** @var LanguageEntity|null $language */
        $language = $this->languageRepository->search($criteria, $context)->get($languageId);

        if ($language === null) {
            throw new LanguageNotFoundException($languageId);
        }

        return $this->localeCache[$languageId] = $language->getLocale()->getCode();
    }
}
