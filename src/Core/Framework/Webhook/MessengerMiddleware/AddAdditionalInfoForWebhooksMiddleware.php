<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\MessengerMiddleware;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandlerArgumentsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

/**
 * @internal
 */
#[Package('core')]
final class AddAdditionalInfoForWebhooksMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (!$envelope->getMessage() instanceof WebhookEventMessage) {
            return $stack->next()->handle($envelope, $stack);
        }

        $envelope = $envelope->with(new HandlerArgumentsStamp(
            $this->resolveInfo($envelope),
        ));

        return $stack->next()->handle($envelope, $stack);
    }

    /**
     * @return array{numRetries: int}
     */
    private function resolveInfo(Envelope $message): array
    {
        return [
            'numRetries' => RedeliveryStamp::getRetryCountFromEnvelope($message),
        ];
    }
}
