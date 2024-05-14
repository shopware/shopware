<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Exception;

use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class InvalidDocumentGeneratorTypeException extends DocumentException
{
}
