<?php declare(strict_types=1);

namespace Shopware\Core\Service\DTO;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\State;

/**
 * @internal
 */
#[Package('framework')]
readonly class Service
{
    /**
     * @param list<string> $requestedPrivileges
     * @param list<string> $privileges
     * @param list<string> $domains
     * @param list<string> $requirements
     */
    private function __construct(
        private string $id,
        private string $name,
        private string $label,
        private bool $active,
        private ?string $icon,
        private ?string $description,
        private \DateTimeInterface $updatedAt,
        private string $version,
        private string $aclRoleId,
        private array $requestedPrivileges,
        private array $privileges,
        private State $state,
        private array $domains,
        private array $requirements,
    ) {
    }

    public static function fromApp(AppEntity $app): self
    {
        $updatedAt = $app->getUpdatedAt() ?? $app->getCreatedAt();
        \assert($updatedAt !== null);

        return new self(
            $app->getId(),
            $app->getName(),
            self::label($app),
            $app->isActive(),
            $app->getIcon(),
            self::description($app),
            $updatedAt,
            $app->getVersion(),
            $app->getAclRoleId(),
            array_values($app->getRequestedPrivileges()),
            array_values($app->getAclRole()?->getPrivileges() ?? []),
            State::state($app),
            array_values($app->getAllowedHosts() ?? []),
            self::requirements($app),
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getAclRoleId(): string
    {
        return $this->aclRoleId;
    }

    /**
     * @return list<string>
     */
    public function getRequestedPrivileges(): array
    {
        return $this->requestedPrivileges;
    }

    /**
     * @return list<string>
     */
    public function getPrivileges(): array
    {
        return $this->privileges;
    }

    /**
     * @return list<string>
     */
    public function getAllPrivileges(): array
    {
        return array_values(array_unique(array_merge(
            $this->getRequestedPrivileges(),
            $this->getPrivileges(),
        )));
    }

    public function getState(): State
    {
        return $this->state;
    }

    /**
     * @return list<string>
     */
    public function getDomains(): array
    {
        return $this->domains;
    }

    /**
     * @return list<string>
     */
    public function getRequirements(): array
    {
        return $this->requirements;
    }

    private static function label(AppEntity $app): string
    {
        $label = $app->getLabel();
        \assert($label !== null);

        return $label;
    }

    private static function description(AppEntity $app): ?string
    {
        $description = $app->getTranslation('description');

        return \is_string($description) ? $description : null;
    }

    /**
     * @return list<string>
     */
    private static function requirements(AppEntity $app): array
    {
        /** @var array{requirements?: list<string>} $sourceConfig */
        $sourceConfig = $app->getSourceConfig();

        return array_values($sourceConfig['requirements'] ?? []);
    }
}
