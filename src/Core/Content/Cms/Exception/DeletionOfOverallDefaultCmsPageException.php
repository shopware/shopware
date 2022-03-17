<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class DeletionOfOverallDefaultCmsPageException extends ShopwareHttpException
{
    public function __construct(string $key)
    {
        parent::__construct(
            'The cms page with id "{{ key }}" is assigned as a default to all sales channels and therefore can not be deleted.',
            ['key' => $key]
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_CONFLICT;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__DELETION_OVERALL_DEFAULT_CMS_PAGE';
    }
}
