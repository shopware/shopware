<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
use Shopware\Core\Framework\Store\Exception\ShopSecretInvalidException;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @package merchant-services
 *
 * @internal
 */
class ShopSecretInvalidMiddleware implements MiddlewareInterface
{
    private const INVALID_SHOP_SECRET = 'ShopwarePlatformException-68';

    private Connection $connection;

    private SystemConfigService $systemConfigService;

    /**
     * @internal
     */
    public function __construct(Connection $connection, SystemConfigService $systemConfigService)
    {
        $this->connection = $connection;
        $this->systemConfigService = $systemConfigService;
    }

    public function __invoke(ResponseInterface $response): ResponseInterface
    {
        if ($response->getStatusCode() !== 401) {
            return $response;
        }

        $body = json_decode($response->getBody()->getContents(), true, 512, \JSON_THROW_ON_ERROR);
        $code = $body['code'] ?? null;

        if ($code !== self::INVALID_SHOP_SECRET) {
            $response->getBody()->rewind();

            return $response;
        }

        $this->connection->executeStatement('UPDATE user SET store_token = NULL');

        $this->systemConfigService->delete(StoreRequestOptionsProvider::CONFIG_KEY_STORE_SHOP_SECRET);

        throw new ShopSecretInvalidException();
    }
}
