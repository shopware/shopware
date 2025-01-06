<?php declare(strict_types=1);

namespace Shopware\Core\Content\LandingPage\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.7.0 - Will be removed. Use \Shopware\Core\Content\LandingPage\LandingPageException::notFound instead
 */
#[Package('buyers-experience')]
class LandingPageNotFoundException extends ShopwareHttpException
{
    public function __construct(string $landingPageId)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', 'LandingPageException::notFound')
        );
        parent::__construct(
            'Landing page "{{ landingPageId }}" not found.',
            ['landingPageId' => $landingPageId]
        );
    }

    public function getStatusCode(): int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', 'LandingPageException::notFound')
        );

        return Response::HTTP_NOT_FOUND;
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', 'LandingPageException::notFound')
        );

        return 'CONTENT__LANDING_PAGE_NOT_FOUND';
    }
}
