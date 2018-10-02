<?php declare(strict_types=1);

namespace Shopware\Storefront\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class MinimumSearchTermLengthNotGiven extends ShopwareHttpException
{
    /**
     * @var string
     */
    protected $fetchMode;

    protected $code = 'SEARCH-TERM-TOO-SHORT';

    public function __construct($code = 0, \Throwable $previous = null)
    {
        $message = 'Search term is too short.';
        parent::__construct($message, $code, $previous);
    }

    public function getFetchMode(): string
    {
        return $this->fetchMode;
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
