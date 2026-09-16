<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\EntitySync;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\UsageData\Services\EntityDispatchService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('data-services')]
#[AsMessageHandler(handles: CollectEntityDataMessage::class)]
final readonly class CollectEntityDataMessageHandler
{
    public function __construct(
        private EntityDispatchService $entityDispatchService,
    ) {
    }

    public function __invoke(CollectEntityDataMessage $message): void
    {
        $this->entityDispatchService->dispatchIterateEntityMessages($message);
    }
}
