<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleInterface;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TaxRuleNotSupportedException extends ShopwareHttpException
{
    /**
     * @var TaxRuleInterface
     */
    protected $taxRule;

    protected $code = 'TAX-RULE-NOT-SUPPORTED';

    public function __construct(TaxRuleInterface $taxRule, $code = 0, Throwable $previous = null)
    {
        $message = sprintf('Tax rule %s not supported', \get_class($taxRule));
        parent::__construct($message, $code, $previous);
        $this->taxRule = $taxRule;
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_IMPLEMENTED;
    }

    public function getTaxRule(): TaxRuleInterface
    {
        return $this->taxRule;
    }
}
