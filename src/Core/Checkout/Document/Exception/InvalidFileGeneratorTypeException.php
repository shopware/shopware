<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidFileGeneratorTypeException extends ShopwareHttpException
{
    protected $code = 'INVALID-FILE-GENERATOR-TYPE';

    public function __construct(string $type, $code = 0)
    {
        $message = sprintf('Unable to find a file generator with type "%s"', $type);
        parent::__construct($message, $code);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return $this->code;
    }
}
