<?php declare(strict_types=1);

namespace Shopware\Core\Service\ServiceRegistry;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class SaveConsentRequest implements \JsonSerializable
{
    public function __construct(
        public string $identifier,
        public string $consentingUserId,
        public string $shopIdentifier,
        public string $consentDate,
        public string $consentRevision,
        public ?string $licenseHost = null,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'identifier' => $this->identifier,
            'consentingUserId' => $this->consentingUserId,
            'shopIdentifier' => $this->shopIdentifier,
            'consentDate' => $this->consentDate,
            'consentRevision' => $this->consentRevision,
            'licenseHost' => $this->licenseHost,
        ];
    }
}
