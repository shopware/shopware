<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Command;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class WriteTypeIntendException extends ShopwareHttpException
{
    public function __construct(
        EntityDefinition $definition,
        string $expectedClass,
        string $actualClass
    ) {
        $hint = match ([$expectedClass, $actualClass]) {
            [UpdateCommand::class, InsertCommand::class] => 'Hint: Use POST method to create new entities.',
            default => '',
        };
        parent::__construct(
            'Expected command for "{{ definition }}" to be "{{ expectedClass }}". (Got: {{ actualClass }})' . $hint,
            ['definition' => $definition->getEntityName(), 'expectedClass' => $expectedClass, 'actualClass' => $actualClass]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__WRITE_TYPE_INTEND_ERROR';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
