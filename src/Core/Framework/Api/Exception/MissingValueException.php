<?php declare(strict_types=1);

namespace Shopware\Framework\Api\Exception;

use Shopware\Framework\ShopwareException;
use Symfony\Component\Serializer\Exception\MappingException;
use Throwable;

class MissingValueException extends MappingException implements ShopwareException
{
    /**
     * @var string
     */
    private $fieldName;

    /**
     * {@inheritdoc}
     */
    public function __construct(string $fieldName, int $code = 0, Throwable $previous = null)
    {
        $this->fieldName = $fieldName;

        $message = sprintf('Missing data for field "%s".', $this->fieldName);

        parent::__construct($message, $code, $previous);
    }

    public function getFieldName(): string
    {
        return $this->fieldName;
    }
}
