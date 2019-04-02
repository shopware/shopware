<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Language\LanguageEntity;

class CurrencyFormatter
{
    /**
     * @var EntityRepositoryInterface
     */
    protected $languageRepository;

    /**
     * @var array
     */
    protected $localeCache = [];

    public function __construct(EntityRepositoryInterface $languageRepository)
    {
        $this->languageRepository = $languageRepository;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function formatCurrencyByLanguage(float $price, string $currency, string $languageId, Context $context): string
    {
        if (!array_key_exists($languageId, $this->localeCache)) {
            $criteria = (new Criteria())->addFilter(new EqualsFilter('language.id', $languageId));
            /** @var LanguageEntity $language */
            $language = $this->languageRepository->search($criteria, $context)->get($languageId);
            // todo throw exception
            $this->localeCache[$languageId] = $language->getLocale()->getCode();
        }

        return $this->formatCurrency($price, $this->localeCache[$languageId], $currency);
    }

    public function formatCurrency(
        float $price,
        string $locale,
        string $currency,
        int $format = \NumberFormatter::CURRENCY,
        ?string $pattern = null
    ): ?string {
        if ($pattern === null) {
            $numberFormatter = new \NumberFormatter($locale, $format);
        } else {
            $numberFormatter = new \NumberFormatter($locale, $format, $pattern);
        }

        return $numberFormatter->formatCurrency($price, $currency);
    }
}
