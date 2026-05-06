<?php declare(strict_types=1);

namespace Shopware\Core\Service\ServiceRegistry;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class RevokeConsentRequest implements \JsonSerializable
{
    public function __construct(
        public string $consentName,
        public string $shopIdentifier,
        public ?string $licenseHost = null,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'consentName' => $this->consentName,
            'shopIdentifier' => $this->shopIdentifier,
            'licenseHost' => $this->licenseHost,
        ];
    }
}
