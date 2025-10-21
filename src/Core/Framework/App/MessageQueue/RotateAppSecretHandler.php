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
        private readonly EntityRepository $appRepository,
        private readonly AppSecretRotationService $rotationService
    ) {
    }

    public function __invoke(RotateAppSecretMessage $message): void
    {
        // Create fresh context for message processing
        $context = Context::createDefaultContext();
        
        $criteria = new Criteria([$message->getAppId()]);
        $criteria->addAssociation('integration');

        /** @var AppEntity|null $app */
        $app = $this->appRepository->search($criteria, $context)->first();

        if ($app === null) {
            // App was deleted before the message was processed
            return;
        }

        $this->rotationService->rotateNow($app, $context, $message->getTrigger());
    }
}
