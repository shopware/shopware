<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Checkout\Document\Exception\InvalidDocumentGeneratorTypeException;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;

#[Package('customer-order')]
class DocumentException extends HttpException
{
    public const INVALID_DOCUMENT_GENERATOR_TYPE_CODE = 'DOCUMENT__INVALID_GENERATOR_TYPE';

    public static function invalidDocumentGeneratorType(string $type): self
    {
        return new InvalidDocumentGeneratorTypeException($type);
    }
}
