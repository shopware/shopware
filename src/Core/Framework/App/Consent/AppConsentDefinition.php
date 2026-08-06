<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Consent;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentDefinition;
use Shopware\Core\System\Consent\ConsentScope;

/**
 * A consent one app declares in its manifest, as the core consent system sees it.
 *
 * @internal only for use by the app-system
 */
#[Package('framework')]
readonly class AppConsentDefinition implements ConsentDefinition
{
    public function __construct(
        private string $appName,
        private ConsentConfig $config,
        private \DateTimeImmutable $since,
    ) {
    }

    /**
     * Answers are stored under this string, so it keeps its meaning after the app is uninstalled.
     */
    public function getName(): string
    {
        return $this->appName . '-' . $this->config->name;
    }

    public function getScopeName(): string
    {
        return $this->config->scope;
    }

    public function getSince(): \DateTimeImmutable
    {
        return $this->since;
    }

    /**
     * The app does not declare who may answer, so each scope uses the same permission its bundled
     * consents use.
     */
    public function getRequiredPermissions(): array
    {
        return match ($this->config->scope) {
            ConsentScope\AdminUser::NAME => ['user.update_profile'],
            default => ['system.system_config'],
        };
    }

    public function getLatestRevision(): ?string
    {
        return $this->config->revision;
    }
}
