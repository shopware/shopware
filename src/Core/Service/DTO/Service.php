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
        public string $id,
        public string $name,
        public string $label,
        public bool $active,
        public ?string $icon,
        public ?string $description,
        public \DateTimeInterface $updatedAt,
        public string $version,
        public string $aclRoleId,
        public array $requestedPrivileges,
        public array $privileges,
        public State $state,
        public array $domains,
        public array $requirements,
        public AppEntity $app,
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
            $app,
        );
    }

    /**
     * @return list<string>
     */
    public function getAllPrivileges(): array
    {
        return array_values(array_unique(array_merge(
            $this->requestedPrivileges,
            $this->privileges,
        )));
    }

    private static function label(AppEntity $app): string
    {
        $label = $app->getTranslation('label');

        return \is_string($label) && $label !== '' ? $label : $app->getName();
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
