<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Exception;

use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.7.0 - Will be removed. Use DocumentException::documentGenerationException instead
 */
#[Package('after-sales')]
class DocumentGenerationException extends DocumentException
{
    public function __construct(string $message = '')
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::DOCUMENT_GENERATION_ERROR,
            \sprintf('Unable to generate document. %s', $message),
            [
                '$message' => $message,
            ],
        );
    }
}
