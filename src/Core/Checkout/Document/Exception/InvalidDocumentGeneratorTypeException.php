<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Exception;

use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('customer-order')]
class InvalidDocumentGeneratorTypeException extends DocumentException
{
    /**
     * @deprecated tag:v6.6.0 - reason:becomes-internal - Use DocumentException::invalidDocumentGeneratorType instead
     */
    public function __construct(string $type)
    {
        // @deprecated tag:v6.6.0 - remove own __construct function and move to DocumentException::invalidDocumentGeneratorType
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            DocumentException::INVALID_DOCUMENT_GENERATOR_TYPE_CODE,
            'Unable to find a document generator with type "{{ type }}"',
            ['type' => $type]
        );
    }
}
