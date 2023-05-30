<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('sales-channel')]
class NoEntitiesForPreviewException extends ShopwareHttpException
{
    final public const ERROR_CODE = 'FRAMEWORK__NO_ENTRIES_FOR_SEO_URL_PREVIEW';

    public function __construct(
        string $entityName,
        string $routeName
    ) {
        parent::__construct(
            'No entites of type {{ entityName }} could be found to create a preview for route {{ routeName }}',
            ['entityName' => $entityName, 'routeName' => $routeName]
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return self::ERROR_CODE;
    }
}
