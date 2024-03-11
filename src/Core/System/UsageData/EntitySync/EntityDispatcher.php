<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\EntitySync;

use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\InstanceService;
use Shopware\Core\Framework\Store\Services\StoreService;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\UsageData\UsageDataException;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('data-services')]
class EntityDispatcher
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly InstanceService $instanceService,
        private readonly SystemConfigService $systemConfigService,
        private readonly ClockInterface $clock,
        private readonly string $environment,
        private readonly bool $dispatchEnabled,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $entities
     */
    public function dispatch(
        string $entityName,
        array $entities,
        Operation $operation,
        \DateTimeImmutable $runDate,
        string $shopId
    ): void {
        if (!$this->dispatchEnabled) {
            return;
        }

        if (empty($entities)) {
            return;
        }

        $payload = json_encode([
            'batch_id' => Uuid::randomHex(),
            'dispatch_date' => $this->clock->now()->format(\DateTimeInterface::ATOM),
            'entities' => $entities,
            'entity' => $entityName,
            'environment' => $this->environment,
            'license_host' => $this->systemConfigService->getString(StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN),
            'operation' => $operation,
            'run_date' => $runDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'shop_id' => $shopId,
            'shopware_version' => $this->instanceService->getShopwareVersion(),
        ], \JSON_THROW_ON_ERROR);

        $payload = gzencode($payload);
        if ($payload === false) {
            throw UsageDataException::failedToCompressEntityDispatchPayload();
        }

        $headers = [
            'Content-Encoding' => 'gzip',
            'Content-Type' => 'application/json',
            'Shopware-Shop-Id' => $shopId,
        ];

        try {
            $this->client->request(
                Request::METHOD_POST,
                '/v1/entities',
                [
                    'headers' => $headers,
                    'body' => $payload,
                ]
            );
        } catch (ServerException|ClientException $exception) { /* @phpstan-ignore-line */
            if (
                $exception->getCode() === Response::HTTP_BAD_GATEWAY
                || $exception->getCode() === Response::HTTP_SERVICE_UNAVAILABLE
                || $exception->getCode() === Response::HTTP_GATEWAY_TIMEOUT
            ) {
                // throw the exception because it might be recoverable after some time
                throw $exception;
            }

            throw new UnrecoverableMessageHandlingException(
                sprintf('Error while dispatching entity: %s', $exception->getMessage()),
                previous: $exception
            );
        }
    }
}
