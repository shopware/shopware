<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class AppUrlVerificationFailedException extends AppException implements AppSystemMisconfigurationException
{
    final public const APP_URL_FAILED_VERIFICATION = 'FRAMEWORK__APP_URL_FAILED_VERIFICATION';

    public function __construct(
        private readonly ShopId $shopId,
        string $appUrl
    ) {
        parent::__construct(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::APP_URL_FAILED_VERIFICATION,
            'App url {{ url }} failed verification',
            ['url' => $appUrl]
        );
    }

    public function getShopId(): ShopId
    {
        return $this->shopId;
    }
}
