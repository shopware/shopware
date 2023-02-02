<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class ExpectedArrayException extends ShopwareHttpException implements WriteFieldException
{
    /**
     * @var string
     */
    private $path;

    public function __construct(string $path)
    {
        parent::__construct('Expected data to be array.');

        $this->path = $path;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__WRITE_MALFORMED_INPUT';
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
