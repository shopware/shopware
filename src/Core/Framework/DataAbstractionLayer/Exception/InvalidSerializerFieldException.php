<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class InvalidSerializerFieldException extends ShopwareHttpException
{
    /**
     * @var string
     */
    private $expectedClass;

    /**
     * @var Field
     */
    private $field;

    public function __construct(string $expectedClass, Field $field, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('Expected field of type %s got %s', $expectedClass, \get_class($field));

        parent::__construct($message, $code, $previous);

        $this->expectedClass = $expectedClass;
        $this->field = $field;
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getField(): Field
    {
        return $this->field;
    }

    public function getExpectedClass(): string
    {
        return $this->expectedClass;
    }
}
