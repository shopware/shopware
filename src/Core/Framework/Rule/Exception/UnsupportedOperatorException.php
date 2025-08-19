<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('fundamentals@after-sales')]
class UnsupportedOperatorException extends ShopwareHttpException
{
    public function __construct(
        protected string $operator,
        protected string $class,
    ) {
        parent::__construct(
            'Unsupported operator {{ operator }} in {{ class }}',
            ['operator' => $this->operator, 'class' => $this->class]
        );
    }

    public function getOperator(): string
    {
        return $this->operator;
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
        return 'CONTENT__RULE_OPERATOR_NOT_SUPPORTED';
    }
}
