<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('customer-order')]
class InvalidFileGeneratorTypeException extends ShopwareHttpException
{
    public function __construct(string $type)
    {
        $message = sprintf('Unable to find a file generator with type "%s"', $type);
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'DOCUMENT__INVALID_FILE_GENERATOR_TYPE';
    }
}
