<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\EntitySync;

use Doctrine\DBAL\ConnectionException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\UsageData\Consent\ConsentService;
use Shopware\Core\System\UsageData\Services\EntityDefinitionService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[AsMessageHandler(handles: IterateEntityMessage::class)]
#[Package('data-services')]
final class IterateEntityMessageHandler
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly IterateEntitiesQueryBuilder $iteratorFactory,
        private readonly ConsentService $consentService,
        private readonly EntityDefinitionService $entityDefinitionService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(IterateEntityMessage $message): void
    {
        if ($message->lastRun === null && $message->operation !== Operation::CREATE) {
            return;
        }

        if ($this->entityDefinitionService->getAllowedEntityDefinition($message->entityName) === null) {
            throw new UnrecoverableMessageHandlingException(sprintf(
                'Entity definition for entity %s not found.',
                $message->entityName,
            ));
        }

        $lastApprovalDate = $this->consentService->getLastConsentIsAcceptedDate();
        if ($lastApprovalDate === null) {
            throw new UnrecoverableMessageHandlingException(sprintf(
                'No approval date found. Skipping dispatching of entity sync message. Entity: %s, Operation: %s',
                $message->entityName,
                $message->operation->value,
            ));
        }

        try {
            $iterator = $this->iteratorFactory->create(
                $message->entityName,
                $message->operation,
                $message->runDate,
                $lastApprovalDate,
                $message->lastRun
            );

            while ($primaryKeys = $iterator->fetchAllAssociative()) {
                $this->bus->dispatch(
                    new DispatchEntityMessage(
                        $message->entityName,
                        $message->operation,
                        $message->runDate,
                        $primaryKeys,
                        $message->shopId
                    )
                );

                $iterator->setFirstResult($iterator->getFirstResult() + $iterator->getMaxResults());
            }
        } catch (ConnectionException|UnrecoverableMessageHandlingException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error(
                'Could not iterate over entity: ' . $e->getMessage(),
                [
                    'exception' => $e,
                    'entity' => $message->entityName,
                    'operation' => $message->operation->value,
                ]
            );
        }
    }
}
