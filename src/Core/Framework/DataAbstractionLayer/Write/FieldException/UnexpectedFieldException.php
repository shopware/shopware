<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class UnexpectedFieldException extends ShopwareHttpException implements WriteFieldException
{
    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $fieldName;

    public function __construct(string $path, string $fieldName)
    {
        parent::__construct(
            'Unexpected field: {{ field }}',
            ['field' => $fieldName]
        );

        $this->path = $path;
        $this->fieldName = $fieldName;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__WRITE_UNEXPECTED_FIELD_ERROR';
    }

    public function getFieldName(): string
    {
        return $this->fieldName;
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
