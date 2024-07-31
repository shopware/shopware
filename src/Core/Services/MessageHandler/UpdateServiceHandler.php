<?php declare(strict_types=1);

namespace Shopware\Core\Services\MessageHandler;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Services\Message\UpdateServiceMessage;
use Shopware\Core\Services\ServiceLifecycle;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('core')]
#[AsMessageHandler]
final readonly class UpdateServiceHandler
{
    public function __construct(private ServiceLifecycle $serviceLifecycle)
    {
    }

    public function __invoke(UpdateServiceMessage $updateServiceMessage): void
    {
        $this->serviceLifecycle->update($updateServiceMessage->name, Context::createDefaultContext());
    }
}
