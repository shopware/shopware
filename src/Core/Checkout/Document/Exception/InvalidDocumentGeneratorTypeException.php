<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Exception;

use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.7.0 - Will be removed, use DocumentException::invalidDocumentGeneratorType instead
 */
#[Package('after-sales')]
class InvalidDocumentGeneratorTypeException extends DocumentException
{
}
