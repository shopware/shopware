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
    public function formatCurrencyByLanguage(float $price, string $currency, string $languageId, Context $context, ?int $decimals = null): string
    {
        $decimals = $decimals ?? $context->getRounding()->getDecimals();

        $locale = $this->getLocale($languageId, $context);
        $formatter = $this->getFormatter($locale, \NumberFormatter::CURRENCY);
        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $decimals);

        return $formatter->formatCurrency($price, $currency);
    }

    private function getFormatter(string $locale, int $format): \NumberFormatter
    {
        $hash = md5(json_encode([$locale, $format]));

        if (isset($this->formatter[$hash])) {
            return $this->formatter[$hash];
        }

        return $this->formatter[$hash] = new \NumberFormatter($locale, $format);
    }

    private function getLocale(string $languageId, Context $context): string
    {
        if (\array_key_exists($languageId, $this->localeCache)) {
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
