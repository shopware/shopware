<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use GuzzleHttp\Client;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class WebhookSenderFactory
{
    public function __construct(
        private readonly Client $client
    ) {
    }

    public function create(): WebhookSender
    {
        return new WebhookSender($this->client);
    }
}
