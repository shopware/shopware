<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Defaults;

use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;

/**
 * Merges a {@see MailTemplateEntity} read from the database with the shipped defaults held by
 * the {@see MailTemplateDefaultsRegistry}.
 *
 * For each translatable field, a non-null database value wins; otherwise the shipped default for
 * the matching locale is used (with en-GB as final fallback inside the registry).
 *
 * The result is a {@see ResolvedMailTemplate} containing concrete strings ready for rendering,
 * together with a per-field provenance map (`user` vs `default`).
 */
#[Package('after-sales')]
class MailTemplateResolver
{
    public function __construct(
        private readonly MailTemplateDefaultsRegistry $registry,
        private readonly LanguageLocaleCodeProvider $languageLocaleProvider,
    ) {
    }

    public function resolve(MailTemplateEntity $mailTemplate, Context $context): ResolvedMailTemplate
    {
        $default = $this->loadDefault($mailTemplate, $context);

        return $this->merge($mailTemplate, $default);
    }

    /**
     * Resolves only the shipped defaults for the given template, without consulting the database.
     * Used by the admin "show me the factory default" endpoint.
     */
    public function resolveDefaults(MailTemplateEntity $mailTemplate, Context $context): ?MailTemplateDefault
    {
        return $this->loadDefault($mailTemplate, $context);
    }

    private function loadDefault(MailTemplateEntity $mailTemplate, Context $context): ?MailTemplateDefault
    {
        $type = $mailTemplate->getMailTemplateType();

        if ($type === null) {
            return null;
        }

        $technicalName = $type->getTechnicalName();

        if (!$this->registry->has($technicalName)) {
            return null;
        }

        $locale = $this->languageLocaleProvider->getLocaleForLanguageId($context->getLanguageId());

        return $this->registry->getDefault($technicalName, $locale);
    }

    private function merge(MailTemplateEntity $mailTemplate, ?MailTemplateDefault $default): ResolvedMailTemplate
    {
        $source = [];

        $subject = $this->pick($mailTemplate->getSubject(), $default?->subject, $source, 'subject');
        $senderName = $this->pick($mailTemplate->getSenderName(), $default?->senderName, $source, 'senderName');
        $description = $this->pick($mailTemplate->getDescription(), $default?->description, $source, 'description');
        $contentHtml = $this->pick($mailTemplate->getContentHtml(), $default?->contentHtml, $source, 'contentHtml');
        $contentPlain = $this->pick($mailTemplate->getContentPlain(), $default?->contentPlain, $source, 'contentPlain');

        return new ResolvedMailTemplate(
            subject: $subject,
            senderName: $senderName,
            description: $description,
            contentHtml: $contentHtml,
            contentPlain: $contentPlain,
            source: $source,
        );
    }

    /**
     * @param array<string, string> $source
     */
    private function pick(?string $userValue, ?string $defaultValue, array &$source, string $field): string
    {
        if ($userValue !== null && $userValue !== '') {
            $source[$field] = ResolvedMailTemplate::SOURCE_USER;

            return $userValue;
        }

        if ($defaultValue !== null) {
            $source[$field] = ResolvedMailTemplate::SOURCE_DEFAULT;

            return $defaultValue;
        }

        $source[$field] = ResolvedMailTemplate::SOURCE_DEFAULT;

        return '';
    }
}
