<?php declare(strict_types=1);

namespace Shopware\Core\Service\MessageHandler;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\Message\UpdateServiceMessage;
use Shopware\Core\Service\ServiceLifecycle;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('framework')]
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
