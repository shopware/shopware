<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class DeletionOfDefaultCmsPageException extends ShopwareHttpException
{
    public function __construct(string $cmsPages)
    {
        parent::__construct(
            'The cms pages with id "{{ cmsPages }}" is assigned as a default and therefore can not be deleted.',
            ['cmsPages' => $cmsPages]
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_CONFLICT;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__DELETION_DEFAULT_CMS_PAGE';
    }
}
