<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Twig\Filter;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\System\Currency\CurrencyFormatter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class CurrencyFilter extends AbstractExtension
{
    /**
     * @var CurrencyFormatter
     */
    private $currencyFormatter;

    public function __construct(CurrencyFormatter $currencyFormatter)
    {
        $this->currencyFormatter = $currencyFormatter;
    }

    public function getFilters()
    {
        return [
            new TwigFilter('currency', [$this, 'formatCurrency'], ['needs_context' => true]),
        ];
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function formatCurrency($context, $price, $currencyIsoCode, $languageId = null)
    {
        if (!array_key_exists('context', $context)
            || (
                !$context['context'] instanceof Context
                && !$context['context'] instanceof SalesChannelContext
            )
        ) {
            throw new \RuntimeException('Error while processing Twig currency filter. No context or locale given.');
        }

        /** @var Context $context */
        if ($context['context'] instanceof Context) {
            $context = $context['context'];
        } else {
            $context = $context['context']->getContext();
        }

        if ($languageId === null) {
            $languageId = $context->getLanguageId();
        }

        return $this->currencyFormatter->formatCurrencyByLanguage($price, $currencyIsoCode, $languageId, $context);
    }
}
