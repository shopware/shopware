<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\Provider;

use Shopware\Core\Framework\AdminAuth\AdminAuthException;
use Shopware\Core\Framework\AdminAuth\Entity\Provider\AdminAuthProviderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Rejects every create/update/delete on `admin_auth_provider` while providers are managed via the
 * YAML configuration (`shopware.admin_auth.providers`) or the provider admin UI is disabled
 * (`shopware.admin_auth.admin_ui: false`) — the API must not be a backdoor around either.
 *
 * @internal
 */
#[Package('framework')]
class ProviderWriteValidator implements EventSubscriberInterface
{
    public function __construct(private readonly ProviderRegistry $providerRegistry)
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
        if (!Feature::isActive('ADMIN_AUTH') || $this->providerRegistry->isAdminUiEnabled()) {
            return;
        }

        foreach ($event->getCommands() as $command) {
            if ($command->getEntityName() !== AdminAuthProviderDefinition::ENTITY_NAME) {
                continue;
            }

            $event->getExceptions()->add(AdminAuthException::providersNotWritable());

            return;
        }
    }
}
