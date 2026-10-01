<?php declare(strict_types=1);

namespace Shopware\Tests\Migration;

/**
 * @internal
 */
class MailTemplateTranslationResult
{
    public readonly string $mailTemplateTypeTechnicalName;

    public readonly string $mailTemplateTypeId;

    public readonly string $mailTemplateId;

    public readonly Translations $translations;

    public function __construct(
        string $mailTemplateTypeTechnicalName,
        string $mailTemplateTypeId,
        string $mailTemplateId,
        Translations $translations
    ) {
        $this->mailTemplateTypeTechnicalName = $mailTemplateTypeTechnicalName;
        $this->mailTemplateTypeId = $mailTemplateTypeId;
        $this->mailTemplateId = $mailTemplateId;
        $this->translations = $translations;
    }
}
