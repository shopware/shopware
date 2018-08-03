<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Context\CheckoutContextService;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Routing\Exception\SalesChannelNotFoundException;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class SalesChannelRequestContextResolver implements RequestContextResolverInterface
{
    /**
     * @var RequestContextResolverInterface
     */
    private $decorated;

    /**
     * @var CheckoutContextService
     */
    private $contextService;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(
        RequestContextResolverInterface $decorated,
        CheckoutContextService $contextService,
        Connection $connection
    ) {
        $this->decorated = $decorated;
        $this->contextService = $contextService;
        $this->connection = $connection;
    }

    public function resolve(SymfonyRequest $master, SymfonyRequest $request): void
    {
        if (!$master->attributes->has(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID)) {
            $this->decorated->resolve($master, $request);

            return;
        }

        $clientId = $master->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID);

        if ($clientId === 'administration') {
            $this->decorated->resolve($master, $request);

            return;
        }

        $origin = AccessKeyHelper::getOrigin($clientId);

        if ($origin !== 'sales-channel') {
            $this->decorated->resolve($master, $request);

            return;
        }

        if (!$master->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN)) {
            $master->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, Uuid::uuid4()->getHex());
        }
        if (!$master->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN)) {
            try {
                $this->decorated->resolve($master, $request);
            } catch (\Exception $e) {
                throw new \RuntimeException('No context token detected', 400, $e);
            }

            return;
        }

        $contextToken = $master->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        $tenantId = $master->headers->get(PlatformRequest::HEADER_TENANT_ID);
        $accessKey = $master->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID);
        $salesChannelId = $this->getSalesChannelId($accessKey, $tenantId);

        //sub requests can use the context of the master request
        if ($master->attributes->has(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT)) {
            $context = $master->attributes->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);
        } else {
            $context = $this->contextService->get($tenantId, $salesChannelId, $contextToken);
        }

        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context->getContext());
        $request->attributes->set(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT, $context);
    }

    private function getSalesChannelId(string $accessKey, string $tenantId): string
    {
        $builder = $this->connection->createQueryBuilder();

        $salesChannelId = $builder->select(['sales_channel.id'])
            ->from('sales_channel')
            ->where('sales_channel.tenant_id = :tenantId')
            ->andWhere('sales_channel.access_key = :accessKey')
            ->setParameter('tenantId', Uuid::fromHexToBytes($tenantId))
            ->setParameter('accessKey', $accessKey)
            ->execute()
            ->fetchColumn();

        if (!$salesChannelId) {
            throw new SalesChannelNotFoundException();
        }

        return Uuid::fromBytesToHex($salesChannelId);
    }
}
