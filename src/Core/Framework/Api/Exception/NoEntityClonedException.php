<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Exception;

use Shopware\Core\Framework\ShopwareException;
use Symfony\Component\Serializer\Exception\MappingException;

class NoEntityClonedException extends MappingException implements ShopwareException
{
    /**
     * {@inheritdoc}
     */
    public function __construct(string $entity, string $id, int $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('Could not clone entity %s with id %s', $entity, $id);

        parent::__construct($message, $code, $previous);
    }
}
