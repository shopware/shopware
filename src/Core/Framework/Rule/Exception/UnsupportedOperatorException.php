<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class UnsupportedOperatorException extends ShopwareHttpException
{
    public const CODE = 200002;

    /**
     * @var string
     */
    protected $operator;

    /**
     * @var string
     */
    protected $class;

    public function __construct(string $operator, string $class)
    {
        $this->operator = $operator;
        $this->class = $class;
        parent::__construct(
            sprintf('Unsupported operator %s in %s', $this->operator, $this->class),
            self::CODE
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
}
