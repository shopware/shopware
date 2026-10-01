<?php declare(strict_types=1);

namespace Shopware\Core\Service\MessageHandler;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\Message\LogPermissionToRegistryMessage;
use Shopware\Core\Service\ServiceRegistry\PermissionLogger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('framework')]
#[AsMessageHandler]
final class LogConsentToRegistryHandler
{
    public function __construct(private readonly PermissionLogger $logger)
    {
    }

    public function __invoke(LogPermissionToRegistryMessage $message): void
    {
        $this->logger->logSync($message->permissionsConsent, $message->consentState);
    }
}
