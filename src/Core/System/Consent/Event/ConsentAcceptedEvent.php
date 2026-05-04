<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\Event;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Hookable;

#[Package('data-services')]
readonly class ConsentAcceptedEvent implements Hookable
{
    public function __construct(
        public string $consentName,
        public string $consentScope,
        public string $identifier,
        public string $actor,
        public ?string $revision = null,
    ) {
    }

    public function getName(): string
    {
        return 'consent.' . $this->consentName . '.accepted';
    }

    public function getWebhookPayload(?AppEntity $app = null): array
    {
        return [
            'consentName' => $this->consentName,
            'consentScope' => $this->consentScope,
            'identifier' => $this->identifier,
            'revision' => $this->revision,
        ];
    }

    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool
    {
        return $permissions->isAllowed('consent:' . $this->consentName, 'read');
    }
}
