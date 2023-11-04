<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('system-settings')]
class ProfileWrongTypeException extends ShopwareHttpException
{
    public function __construct(
        string $profileId,
        string $profileType
    ) {
        parent::__construct(
            'The import/export profile with id {{ profileId }} can only be used for {{ profileType }}',
            ['profileId' => $profileId, 'profileType' => $profileType]
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__IMPORT_EXPORT_PROFILE_WRONG_TYPE';
    }
}
