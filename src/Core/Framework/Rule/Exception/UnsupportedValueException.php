<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class UnsupportedValueException extends ShopwareHttpException
{
    protected string $type;

    protected string $class;

    public function __construct(string $type, string $class)
    {
        $this->type = $type;
        $this->class = $class;

        parent::__construct(
            'Unsupported value of type {{ type }} in {{ class }}',
            ['type' => $type, 'class' => $class]
        );
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__RULE_VALUE_NOT_SUPPORTED';
    }
}
