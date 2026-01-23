<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\Event;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Hookable;

#[Package('data-services')]
readonly class ConsentAcceptedEvent implements Hookable
{
    public const EVENT_NAME = 'consent.accepted';

    public function __construct(
        public string $consentName,
        public string $consentScope,
        public string $identifier,
        public string $actorId
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    /**
     * @return array{consent: string, scope: string, identifier: string|null}
     */
    public function getWebhookPayload(?AppEntity $app = null): array
    {
        return [
            'consent' => $this->consentName,
            'scope' => $this->consentScope,
            'identifier' => $this->identifier,
            'actorId' => $this->actorId,
        ];
    }

    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool
    {
        return true;
    }
}
