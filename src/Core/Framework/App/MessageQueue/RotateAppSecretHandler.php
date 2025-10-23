<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\MessageQueue;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Shopware\Core\Framework\App\Message\RotateAppSecretMessage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\DeduplicatableMessageInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal only for use by the app-system
 */
#[AsMessageHandler]
#[Package('framework')]
class RotateAppSecretHandler
{
    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly AppSecretRotationService $rotationService
    ) {
    }

    public function __invoke(RotateAppSecretMessage $message): void
    {
        $context = Context::createDefaultContext();

        $this->rotationService->rotateNow($message->getAppId(), $context, $message->getTrigger());
    }
}
