<?php declare(strict_types=1);

namespace Shopware\Framework\Api\Exception;

use Shopware\Framework\ShopwareException;
use Symfony\Component\Serializer\Exception\MappingException;
use Throwable;

class MissingDataException extends MappingException implements ShopwareException
{
    /**
     * @var iterable
     */
    private $fields;

    /**
     * {@inheritdoc}
     */
    public function __construct(iterable $fieldNames, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('Missing data for the following properties: %s', implode(', ', $fieldNames));

        parent::__construct($message, $code, $previous);
    }
}
