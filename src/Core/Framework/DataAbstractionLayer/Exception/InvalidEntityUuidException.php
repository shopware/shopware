<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\UuidException;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class InvalidEntityUuidException extends DataAbstractionLayerException
{
    public function __construct(string $uuid)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            'FRAMEWORK__INVALID_UUID',
            'Value is not a valid UUID: {{ uuid }}',
            ['uuid' => $uuid],
            UuidException::invalidUuid($uuid)
        );
    }
}
