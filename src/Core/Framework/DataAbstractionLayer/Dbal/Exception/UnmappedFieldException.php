<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class UnmappedFieldException extends ShopwareHttpException
{
    /**
     * @param string|EntityDefinition $definition
     */
    public function __construct(string $field, string $definition, int $code = 0, \Throwable $previous = null)
    {
        $fieldParts = explode('.', $field);
        $name = array_pop($fieldParts);

        $message = sprintf('Field "%s" in entity "%s" was not found.', $name, $definition::getEntityName());

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
