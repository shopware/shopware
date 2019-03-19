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

    public function __construct(string $message, string $path = '')
    {
        $this->path = $path;

        parent::__construct('{{ message }}', ['message' => $message]);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INVALID_FILTER_QUERY';
    }
}
