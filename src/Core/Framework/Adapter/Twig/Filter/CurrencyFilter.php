<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Filter;

use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Currency\CurrencyFormatter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * @deprecated tag:v6.8.0 - class will be marked internal - reason:becomes-internal
 */
#[Package('framework')]
class CurrencyFilter extends AbstractExtension
{
    /**
     * @internal
     */
    public function __construct(private readonly CurrencyFormatter $currencyFormatter)
    {
    }

    /**
     * @return TwigFilter[]
     */
    public function getFilters()
    {
        return [
            new TwigFilter('currency', $this->formatCurrency(...), ['needs_context' => true]),
        ];
    }

    /**
     * @deprecated tag:v6.8.0 - arguments will be type-hinted - reason:becomes-internal
     *
     * @param array<string, mixed> $twigContext
     * @param float $price
     * @param string|null $currencyIsoCode
     * @param string|null $languageId
     *
     * @throws InconsistentCriteriaIdsException
     *
     * @return float|string
     */
    public function formatCurrency($twigContext, $price, $currencyIsoCode = null, $languageId = null, ?int $decimals = null)
    {
        if (!\array_key_exists('context', $twigContext)
            || (
                !$twigContext['context'] instanceof Context
                && !$twigContext['context'] instanceof SalesChannelContext
            )
        ) {
            if (isset($twigContext['testMode']) && $twigContext['testMode'] === true) {
                return $price;
            }

            throw AdapterException::currencyFilterMissingContext();
        }

        if (!$currencyIsoCode && $twigContext['context'] instanceof SalesChannelContext) {
            $currencyIsoCode = $twigContext['context']->getCurrency()->getIsoCode();
        }

        if (!$currencyIsoCode) {
            if (isset($twigContext['testMode']) && $twigContext['testMode'] === true) {
                return $price;
            }

            throw AdapterException::currencyFilterMissingIsoCode();
        }

        if ($twigContext['context'] instanceof Context) {
            $context = $twigContext['context'];
        } else {
            $context = $twigContext['context']->getContext();
        }

        if ($languageId === null) {
            $languageId = $context->getLanguageId();
        }

        if ($price === null) {
            $price = 0.0;
        }

        return $this->currencyFormatter->formatCurrencyByLanguage($price, $currencyIsoCode, $languageId, $context, $decimals);
    }
}
