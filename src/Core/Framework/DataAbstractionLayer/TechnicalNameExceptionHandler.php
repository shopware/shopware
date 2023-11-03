<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Checkout\Shipping\ShippingException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class TechnicalNameExceptionHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_DEFAULT;
    }

    public function matchException(\Exception $e): ?\Exception
    {
        if (\preg_match(
            '/SQLSTATE\[23000]: Integrity constraint violation: 1062 Duplicate entry \'(?<technicalName>.*)\' for key \'payment_method.uniq\.technical_name\'/',
            $e->getMessage(),
            $matches
        )) {
            if (\array_key_exists('technicalName', $matches) && \is_string($matches['technicalName'])) {
                return PaymentException::duplicateTechnicalName($matches['technicalName']);
            }
        }

        if (\preg_match(
            '/SQLSTATE\[23000]: Integrity constraint violation: 1062 Duplicate entry \'(?<technicalName>.*)\' for key \'shipping_method.uniq\.technical_name\'/',
            $e->getMessage(),
            $matches
        )) {
            if (\array_key_exists('technicalName', $matches) && \is_string($matches['technicalName'])) {
                return ShippingException::duplicateTechnicalName($matches['technicalName']);
            }
        }

        return null;
    }
}
