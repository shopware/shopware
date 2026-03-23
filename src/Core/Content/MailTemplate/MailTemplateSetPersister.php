<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\Xml\MailTemplate;
use Shopware\Core\Content\MailTemplate\Xml\MailTemplates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('after-sales')]
class MailTemplateSetPersister
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function sync(MailTemplates $mailTemplates, Context $context): void
    {
        $context->scope(Context::SYSTEM_SCOPE, function () use ($mailTemplates): void {
            $this->upsertMailTemplates($mailTemplates);
        });
    }

    /**
     * @param list<string> $technicalNames
     */
    public function removeByTechnicalNames(array $technicalNames, Context $context): void
    {
        if ($technicalNames === []) {
            return;
        }

        $context->scope(Context::SYSTEM_SCOPE, function () use ($technicalNames): void {
            $typeIds = $this->connection->fetchFirstColumn(
                'SELECT id FROM mail_template_type WHERE technical_name IN (:names)',
                ['names' => $technicalNames],
                ['names' => ArrayParameterType::STRING]
            );

            if ($typeIds === []) {
                return;
            }

            // Delete mail templates first (SetNullOnDelete won't cascade)
            $this->connection->executeStatement(
                'DELETE FROM mail_template WHERE mail_template_type_id IN (:typeIds)',
                ['typeIds' => $typeIds],
                ['typeIds' => ArrayParameterType::STRING]
            );

            $this->connection->executeStatement(
                'DELETE FROM mail_template_type WHERE id IN (:typeIds)',
                ['typeIds' => $typeIds],
                ['typeIds' => ArrayParameterType::STRING]
            );
        });
    }

    private function upsertMailTemplates(MailTemplates $mailTemplates): void
    {
        $localeToLanguage = $this->resolveLanguageIds($mailTemplates);
        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        foreach ($mailTemplates->getMailTemplates() as $mailTemplate) {
            $typeId = $this->upsertMailTemplateType($mailTemplate, $localeToLanguage, $now);
            $this->upsertMailTemplate($mailTemplate, $typeId, $localeToLanguage, $now);
        }
    }

    /**
     * @param array<string, string> $localeToLanguage locale code => binary language ID
     *
     * @return string binary type ID
     */
    private function upsertMailTemplateType(MailTemplate $mailTemplate, array $localeToLanguage, string $now): string
    {
        $existingTypeId = $this->connection->fetchOne(
            'SELECT id FROM mail_template_type WHERE technical_name = :technicalName',
            ['technicalName' => $mailTemplate->getTechnicalName()]
        );

        if ($existingTypeId === false) {
            $typeId = Uuid::randomBytes();

            $this->connection->insert('mail_template_type', [
                'id' => $typeId,
                'technical_name' => $mailTemplate->getTechnicalName(),
                'available_entities' => $mailTemplate->getAvailableEntities() !== []
                    ? json_encode($mailTemplate->getAvailableEntities(), \JSON_THROW_ON_ERROR)
                    : null,
                'created_at' => $now,
            ]);
        } else {
            $typeId = $existingTypeId;

            $update = ['updated_at' => $now];
            if ($mailTemplate->getAvailableEntities() !== []) {
                $update['available_entities'] = json_encode($mailTemplate->getAvailableEntities(), \JSON_THROW_ON_ERROR);
            }

            $this->connection->update('mail_template_type', $update, ['id' => $typeId]);
        }

        // Upsert type translations (name)
        foreach ($mailTemplate->getName() as $locale => $name) {
            if (!isset($localeToLanguage[$locale])) {
                continue;
            }

            $languageId = $localeToLanguage[$locale];

            $exists = $this->connection->fetchOne(
                'SELECT 1 FROM mail_template_type_translation WHERE mail_template_type_id = :typeId AND language_id = :languageId',
                ['typeId' => $typeId, 'languageId' => $languageId]
            );

            if ($exists !== false) {
                $this->connection->update(
                    'mail_template_type_translation',
                    ['name' => $name, 'updated_at' => $now],
                    ['mail_template_type_id' => $typeId, 'language_id' => $languageId]
                );
            } else {
                $this->connection->insert('mail_template_type_translation', [
                    'mail_template_type_id' => $typeId,
                    'language_id' => $languageId,
                    'name' => $name,
                    'created_at' => $now,
                ]);
            }
        }

        return $typeId;
    }

    /**
     * @param array<string, string> $localeToLanguage locale code => binary language ID
     */
    private function upsertMailTemplate(MailTemplate $mailTemplate, string $typeId, array $localeToLanguage, string $now): void
    {
        $existingTemplateId = $this->connection->fetchOne(
            'SELECT id FROM mail_template WHERE mail_template_type_id = :typeId',
            ['typeId' => $typeId]
        );

        if ($existingTemplateId === false) {
            $templateId = Uuid::randomBytes();

            $this->connection->insert('mail_template', [
                'id' => $templateId,
                'mail_template_type_id' => $typeId,
                'system_default' => 1,
                'created_at' => $now,
            ]);
        } else {
            $templateId = $existingTemplateId;

            $this->connection->update(
                'mail_template',
                ['updated_at' => $now],
                ['id' => $templateId]
            );
        }

        // Collect all locales across all translatable fields and content
        $allLocales = array_unique(array_merge(
            array_keys($mailTemplate->getSubject()),
            array_keys($mailTemplate->getSenderName()),
            array_keys($mailTemplate->getDescription()),
            array_keys($mailTemplate->getContentHtml()),
            array_keys($mailTemplate->getContentPlain()),
        ));

        foreach ($allLocales as $locale) {
            if (!isset($localeToLanguage[$locale])) {
                continue;
            }

            $languageId = $localeToLanguage[$locale];

            $translationData = array_filter([
                'subject' => $mailTemplate->getSubject()[$locale] ?? null,
                'sender_name' => $mailTemplate->getSenderName()[$locale] ?? null,
                'description' => $mailTemplate->getDescription()[$locale] ?? null,
                'content_html' => $mailTemplate->getContentHtml()[$locale] ?? null,
                'content_plain' => $mailTemplate->getContentPlain()[$locale] ?? null,
            ], static fn ($value) => $value !== null);

            if ($translationData === []) {
                continue;
            }

            $exists = $this->connection->fetchOne(
                'SELECT 1 FROM mail_template_translation WHERE mail_template_id = :templateId AND language_id = :languageId',
                ['templateId' => $templateId, 'languageId' => $languageId]
            );

            if ($exists !== false) {
                $translationData['updated_at'] = $now;

                $this->connection->update(
                    'mail_template_translation',
                    $translationData,
                    ['mail_template_id' => $templateId, 'language_id' => $languageId]
                );
            } else {
                $translationData['mail_template_id'] = $templateId;
                $translationData['language_id'] = $languageId;
                $translationData['created_at'] = $now;

                $this->connection->insert('mail_template_translation', $translationData);
            }
        }
    }

    /**
     * Resolves all locale codes used in the mail templates to binary language IDs.
     *
     * @return array<string, string> locale code => binary language ID
     */
    private function resolveLanguageIds(MailTemplates $mailTemplates): array
    {
        $locales = [];

        foreach ($mailTemplates->getMailTemplates() as $mailTemplate) {
            $locales = array_merge(
                $locales,
                array_keys($mailTemplate->getName()),
                array_keys($mailTemplate->getSubject()),
                array_keys($mailTemplate->getSenderName()),
                array_keys($mailTemplate->getDescription()),
                array_keys($mailTemplate->getContentHtml()),
                array_keys($mailTemplate->getContentPlain()),
            );
        }

        $locales = array_unique($locales);

        if ($locales === []) {
            return [];
        }

        /** @var array<string, string> $mapping */
        $mapping = $this->connection->fetchAllKeyValue(
            'SELECT locale.code, language.id
             FROM language
             INNER JOIN locale ON locale.id = language.locale_id
             WHERE locale.code IN (:codes)',
            ['codes' => $locales],
            ['codes' => ArrayParameterType::STRING]
        );

        // Fallback: if en-GB is not mapped to a language, use the system default
        if (!isset($mapping['en-GB'])) {
            $mapping['en-GB'] = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        }

        return $mapping;
    }
}
