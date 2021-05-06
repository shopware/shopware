<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Handler;

use GuzzleHttp\Client;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;

/**
 * @internal (flag:FEATURE_NEXT_14363) only for use by the app-system
 */
class WebhookEventMessageHandler extends AbstractMessageHandler
{
    private const TIMEOUT = 20;
    private const CONNECT_TIMEOUT = 10;

    private Client $client;

    private EntityRepositoryInterface $appRepository;

    public function __construct(Client $client, EntityRepositoryInterface $appRepository)
    {
        $this->client = $client;
        $this->appRepository = $appRepository;
    }

    /**
     * @param WebhookEventMessage $message
     */
    public function handle($message): void
    {
        $appId = $message->getAppId();
        $shopwareVersion = $message->getShopwareVersion();
        $payload = $message->getPayload();
        $url = $message->getUrl();

        $payload['timestamp'] = time();

        $header = [
            'Content-Type' => 'application/json',
            'sw-version' => $shopwareVersion,
        ];

        /** @var string $jsonPayload */
        $jsonPayload = json_encode($payload);

        $appSecret = $this->getAppSecret($appId);
        if ($appSecret !== null) {
            $header['shopware-shop-signature'] = hash_hmac('sha256', $jsonPayload, $appSecret);
        }

        $this->client->post(
            $url,
            [
                'headers' => $header,
                'body' => $jsonPayload,
                'connect_timeout' => self::CONNECT_TIMEOUT,
                'timeout' => self::TIMEOUT,
            ]
        );
    }

    public static function getHandledMessages(): iterable
    {
        return [WebhookEventMessage::class];
    }

    private function getAppSecret(string $appId): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $appId));

        /** @var AppEntity|null $app */
        $app = $this->appRepository->search($criteria, Context::createDefaultContext())->first();

        return $app ? $app->getAppSecret() : null;
    }
}
