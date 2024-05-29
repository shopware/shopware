<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Api;

use Doctrine\DBAL\Connection;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\InAppPurchase;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('core')]
class AppJWTGenerateRoute
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ShopIdProvider $shopIdProvider
    ) {
    }

    #[Route('/store-api/app-system/{name}/generate-token', name: 'store-api.app-system.generate-token', methods: ['POST'])]
    public function generate(string $name, SalesChannelContext $context): JsonResponse
    {
        if ($context->getCustomer() === null) {
            throw AppException::jwtGenerationRequiresCustomerLoggedIn();
        }

        ['id' => $appId, 'app_secret' => $appSecret, 'privileges' => $privileges] = $this->fetchAppDetails($name);

        $key = InMemory::plainText($appSecret);

        $configuration = Configuration::forSymmetricSigner(
            new Sha256(),
            $key
        );

        $expiration = new \DateTimeImmutable('+10 minutes');

        /** @var non-empty-string $shopId */
        $shopId = $this->shopIdProvider->getShopId();
        $builder = $configuration
            ->builder()
            ->issuedBy($shopId)
            ->issuedAt(new \DateTimeImmutable())
            ->canOnlyBeUsedAfter(new \DateTimeImmutable())
            ->expiresAt($expiration);

        $builder = $builder->withClaim('inAppPurchases', InAppPurchase::getByExtension($appId));

        if (\in_array('sales_channel:read', $privileges, true)) {
            $builder = $builder->withClaim('salesChannelId', $context->getSalesChannel()->getId());
        }

        if (\in_array('customer:read', $privileges, true)) {
            $builder = $builder->withClaim('customerId', $context->getCustomer()->getId());
        }

        if (\in_array('currency:read', $privileges, true)) {
            $builder = $builder->withClaim('currencyId', $context->getCurrency()->getId());
        }

        if (\in_array('language:read', $privileges, true)) {
            $builder = $builder->withClaim('languageId', $context->getLanguageId());
        }

        if (\in_array('payment_method:read', $privileges, true)) {
            $builder = $builder->withClaim('paymentMethodId', $context->getPaymentMethod()->getId());
        }

        if (\in_array('shipping_method:read', $privileges, true)) {
            $builder = $builder->withClaim('shippingMethodId', $context->getShippingMethod()->getId());
        }

        return new JsonResponse([
            'token' => $builder->getToken($configuration->signer(), $configuration->signingKey())->toString(),
            'expires' => $expiration->format(\DateTime::ATOM),
            'shopId' => $shopId,
        ]);
    }

    /**
     * @return array{id: non-empty-string, app_secret: non-empty-string, privileges: array<string>}
     */
    private function fetchAppDetails(string $name): array
    {
        $row = $this->connection->fetchAssociative('SELECT
    LOWER(HEX(`app`.id)) as id,
    `app`.app_secret,
    `acl_role`.privileges
FROM `app`
LEFT JOIN acl_role ON app.acl_role_id = acl_role.id
WHERE `app`.name = ? AND
      active = 1', [$name]);

        if (empty($row)) {
            throw AppException::notFound($name);
        }

        $row['privileges'] = json_decode($row['privileges'], true, 512, \JSON_THROW_ON_ERROR);

        /** @phpstan-ignore-next-line PHPStan could not recognize the loaded array shape from the database */
        return $row;
    }
}
