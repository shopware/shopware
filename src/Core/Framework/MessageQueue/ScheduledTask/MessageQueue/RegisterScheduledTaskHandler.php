<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask\MessageQueue;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @final
 *
 * @internal
 *
 * @deprecated tag:v6.8.0 - Will be removed as the message was not dispatched anymore, call TaskRegistry synchronously
 */
#[AsMessageHandler]
#[Package('framework')]
class RegisterScheduledTaskHandler
{
    /**
     * @internal
     */
    public function __construct(private readonly TaskRegistry $registry)
    {
    }

    public function __invoke(RegisterScheduledTaskMessage $message): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            'Dispatching RegisterScheduledTaskMessage is deprecated and will be removed in v6.8.0.0, call TaskRegistry synchronously instead.'
        );

        $this->registry->registerTasks();
    }
}
