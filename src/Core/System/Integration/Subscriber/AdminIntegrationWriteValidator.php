<?php declare(strict_types=1);

namespace Shopware\Core\System\Integration\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Integration\IntegrationDefinition;
use Shopware\Core\System\Integration\IntegrationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\Api\Controller\IntegrationControllerTest
 */
#[Package('fundamentals@framework')]
class AdminIntegrationWriteValidator implements EventSubscriberInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
        ];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        $context = $event->getContext();
        $source = $context->getSource();

        if ($context->getScope() === Context::SYSTEM_SCOPE || !$source instanceof AdminApiSource || $source->isAdmin()) {
            return;
        }

        $ids = [];
        foreach ($event->getExplicitCommandsForEntity(IntegrationDefinition::ENTITY_NAME) as $command) {
            $ids[] = $command->getPrimaryKey()['id'];
        }

        if ($ids === []) {
            return;
        }

        // only an admin may change or delete an admin integration
        $adminIds = $this->connection->fetchFirstColumn(
            'SELECT id FROM integration WHERE id IN (:ids) AND admin = 1',
            ['ids' => $ids],
            ['ids' => ArrayParameterType::BINARY]
        );

        if ($adminIds !== []) {
            throw IntegrationException::adminIntegrationChangeNotAllowed();
        }
    }
}
