<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Defaults;

use Shopware\Core\Content\MailTemplate\MailTemplateLoader;
use Shopware\Core\Content\MailTemplate\Xml\MailTemplate as XmlMailTemplate;
use Shopware\Core\Content\MailTemplate\Xml\MailTemplates;
use Shopware\Core\Framework\Log\Package;

/**
 * Holds the shipped default content for mail templates, keyed by technical name and locale.
 *
 * Defaults are resolved from XML+twig files under `Resources/mail-templates/` directories belonging to
 * the core platform, installed plugins, and installed apps.
 *
 * The registry is the source of truth for "what does this mail template look like if the merchant has not
 * overridden it". The `mail_template_translation` table stores only the merchant's overrides on top of these.
 */
#[Package('after-sales')]
class MailTemplateDefaultsRegistry
{
    private const CORE_FALLBACK_LOCALE = 'en-GB';

    /**
     * @var array<string, array<string, MailTemplateDefault>> technicalName => locale => default
     */
    private array $defaults = [];

    private bool $coreLoaded = false;

    public function __construct(
        private readonly string $corePath = __DIR__ . '/../Resources/mail-templates'
    ) {
    }

    /**
     * @return list<string>
     */
    public function getTechnicalNames(): array
    {
        $this->ensureCoreLoaded();

        return array_keys($this->defaults);
    }

    public function has(string $technicalName): bool
    {
        $this->ensureCoreLoaded();

        return isset($this->defaults[$technicalName]);
    }

    /**
     * @return list<string>
     */
    public function getLocales(string $technicalName): array
    {
        $this->ensureCoreLoaded();

        if (!isset($this->defaults[$technicalName])) {
            return [];
        }

        return array_keys($this->defaults[$technicalName]);
    }

    /**
     * Returns the shipped default for the given technical name and locale.
     *
     * Falls back to en-GB when the requested locale is not registered. Returns null only when the technical name
     * is unknown to the registry (i.e. no plugin/app/core source has declared it).
     */
    public function getDefault(string $technicalName, string $locale): ?MailTemplateDefault
    {
        $this->ensureCoreLoaded();

        if (!isset($this->defaults[$technicalName])) {
            return null;
        }

        return $this->defaults[$technicalName][$locale]
            ?? $this->defaults[$technicalName][self::CORE_FALLBACK_LOCALE]
            ?? null;
    }

    /**
     * Adds a set of mail templates to the registry. Used by plugin/app lifecycle hooks to register their
     * declared templates at install/update time. Later registrations for the same technical name + locale
     * overwrite earlier ones.
     */
    public function register(MailTemplates $mailTemplates): void
    {
        $this->ensureCoreLoaded();

        foreach ($mailTemplates->getMailTemplates() as $mailTemplate) {
            $this->mergeTemplate($mailTemplate);
        }
    }

    /**
     * Removes all defaults for the given technical names. Called by plugin/app uninstall lifecycle.
     *
     * @param list<string> $technicalNames
     */
    public function remove(array $technicalNames): void
    {
        $this->ensureCoreLoaded();

        foreach ($technicalNames as $technicalName) {
            unset($this->defaults[$technicalName]);
        }
    }

    /**
     * Drops all registered defaults so the next call reloads core templates from disk. Intended for test
     * environments and cache warming.
     */
    public function reset(): void
    {
        $this->defaults = [];
        $this->coreLoaded = false;
    }

    private function ensureCoreLoaded(): void
    {
        if ($this->coreLoaded) {
            return;
        }

        $this->coreLoaded = true;

        if (!is_dir($this->corePath) || !is_file($this->corePath . '/mail-templates.xml')) {
            return;
        }

        $this->register(MailTemplateLoader::load($this->corePath));
    }

    private function mergeTemplate(XmlMailTemplate $mailTemplate): void
    {
        $technicalName = $mailTemplate->getTechnicalName();

        $locales = array_unique(array_merge(
            array_keys($mailTemplate->getSubject()),
            array_keys($mailTemplate->getSenderName()),
            array_keys($mailTemplate->getDescription()),
            array_keys($mailTemplate->getContentHtml()),
            array_keys($mailTemplate->getContentPlain()),
        ));

        foreach ($locales as $locale) {
            $this->defaults[$technicalName][$locale] = new MailTemplateDefault(
                technicalName: $technicalName,
                locale: $locale,
                subject: $mailTemplate->getSubject()[$locale] ?? null,
                senderName: $mailTemplate->getSenderName()[$locale] ?? null,
                description: $mailTemplate->getDescription()[$locale] ?? null,
                contentHtml: $mailTemplate->getContentHtml()[$locale] ?? null,
                contentPlain: $mailTemplate->getContentPlain()[$locale] ?? null,
            );
        }
    }
}
