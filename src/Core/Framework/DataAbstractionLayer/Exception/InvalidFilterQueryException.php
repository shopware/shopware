<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidFilterQueryException extends ShopwareHttpException
{
    /**
     * @var string
     */
    private $path;

    public function __construct(string $message, string $path = '', int $code = 0, \Throwable $previous = null)
    {
        $this->path = $path;

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
