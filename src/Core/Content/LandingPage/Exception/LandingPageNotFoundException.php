<?php declare(strict_types=1);

namespace Shopware\Core\Content\LandingPage\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('content')]
class LandingPageNotFoundException extends ShopwareHttpException
{
    public function __construct(string $landingPageId)
    {
        parent::__construct(
            'Landing page "{{ landingPageId }}" not found.',
            ['landingPageId' => $landingPageId]
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__LANDING_PAGE_NOT_FOUND';
    }
}
