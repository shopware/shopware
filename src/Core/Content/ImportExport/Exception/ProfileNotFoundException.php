<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('system-settings')]
class ProfileNotFoundException extends ShopwareHttpException
{
    public function __construct(string $profileId)
    {
        parent::__construct('Cannot find import/export profile with id {{ profileId }}', ['profileId' => $profileId]);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__IMPORT_EXPORT_PROFILE_NOT_FOUND';
    }
}
