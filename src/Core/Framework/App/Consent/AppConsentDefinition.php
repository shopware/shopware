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
     * The app name and the declared name, so two apps declaring the same name stay separate and
     * neither can take over a core consent. Answers are stored under this string and therefore
     * keep their meaning after the app is uninstalled.
     */
    public function getName(): string
    {
        return $this->appName . '-' . $this->config->name;
    }

    public function getScopeName(): string
    {
        return $this->config->scope;
    }

    /**
     * The date the app declared the consent in this shop.
     */
    public function getSince(): \DateTimeImmutable
    {
        return $this->since;
    }

    /**
     * The same permissions the bundled consents require for the scope: answering for one admin user
     * is a profile change, answering for the whole shop is a system configuration change. The manifest
     * allows no scopes besides those two.
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
