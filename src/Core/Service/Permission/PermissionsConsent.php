<?php declare(strict_types=1);

namespace Shopware\Core\Service\Permission;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\ServiceException;

/**
 * @internal
 */
#[Package('framework')]
class PermissionsConsent implements \JsonSerializable
{
    public function __construct(
        public string $identifier,
        public string $revision,
        public string $consentingUserId,
        public \DateTimeInterface $grantedAt,
    ) {
    }

    /**
     * @throws ServiceException|\Exception
     */
    public static function fromJsonString(string $json): self
    {
        $json = json_decode($json, true);
        if (!\is_array($json)) {
            throw ServiceException::noCurrentPermissionsConsent();
        }

        if (!isset($json['identifier'], $json['revision'], $json['consentingUserId'], $json['grantedAt'])) {
            throw ServiceException::invalidPermissionConsentFormat($json);
        }

        return new self(
            $json['identifier'],
            $json['revision'],
            $json['consentingUserId'],
            new \DateTime($json['grantedAt'])
        );
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return [
            'identifier' => $this->identifier,
            'revision' => $this->revision,
            'consentingUserId' => $this->consentingUserId,
            'grantedAt' => $this->grantedAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
