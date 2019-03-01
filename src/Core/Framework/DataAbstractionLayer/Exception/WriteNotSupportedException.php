<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class WriteNotSupportedException extends ShopwareHttpException
{
    /**
     * @var Field
     */
    private $field;

    public function __construct(Field $field, int $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('Writing to ReadOnly field %s is not supported', \get_class($field));

        parent::__construct($message, $code, $previous);

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
}
