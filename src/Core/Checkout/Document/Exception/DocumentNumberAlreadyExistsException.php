<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Exception;

use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.7.0 - Will be removed. Use DocumentException::documentNumberAlreadyExistsException instead
 */
#[Package('after-sales')]
class DocumentNumberAlreadyExistsException extends DocumentException
{
    public function __construct(?string $number)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::DOCUMENT_NUMBER_ALREADY_EXISTS,
            \sprintf('Document number %s has already been allocated.', $number),
            [
                '$number' => $number,
            ],
        );
    }
}
