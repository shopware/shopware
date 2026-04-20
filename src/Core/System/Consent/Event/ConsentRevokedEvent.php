<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\Event;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Hookable;

#[Package('data-services')]
readonly class ConsentRevokedEvent implements Hookable
{
    public function __construct(
        public string $consentName,
        public string $consentScope,
        public string $identifier,
        public string $actor,
    ) {
    }

    public function getName(): string
    {
        return 'consent.' . $this->consentName . '.revoked';
    }

    public function getWebhookPayload(?AppEntity $app = null): array
    {
        return [
            'consentName' => $this->consentName,
            'consentScope' => $this->consentScope,
            'identifier' => $this->identifier,
        ];
    }

    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool
    {
        return $permissions->isAllowed('consent:' . $this->consentName, 'read');
    }
}
