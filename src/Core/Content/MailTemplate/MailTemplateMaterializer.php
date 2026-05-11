<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\Defaults\MailTemplateDefaultsRegistry;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Creates the parent `mail_template_type` and `mail_template` rows for a registry-only template the
 * first time something concretely needs a UUID for it — e.g. when a merchant saves an override, a
 * flow action picks the template, or a sales-channel-specific copy is created.
 *
 * Plugin and app installs do not call this; they only register their templates with
 * {@see MailTemplateDefaultsRegistry}. Until a UUID is needed, the database stays untouched.
 *
 * Calls are idempotent and safe under concurrency: the `technical_name` unique constraint on
 * `mail_template_type` plus a re-read after the conditional insert make a concurrent winner
 * visible to the loser.
 */
#[Package('after-sales')]
class MailTemplateMaterializer
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly MailTemplateDefaultsRegistry $registry,
    ) {
    }

    /**
     * Ensures parent rows exist for the given technical name and returns the binary mail_template id.
     * Throws when the technical name is not registered (neither core, plugin, nor app declared it).
     */
    public function ensure(string $technicalName, Context $context): string
    {
        if (!$this->registry->has($technicalName)) {
            throw MailTemplateException::technicalNameNotRegistered($technicalName);
        }

        return $context->scope(Context::SYSTEM_SCOPE, fn (): string => $this->createOrFetch($technicalName));
    }

    private function createOrFetch(string $technicalName): string
    {
        $existingTemplateId = $this->fetchExistingTemplateId($technicalName);
        if ($existingTemplateId !== null) {
            return $existingTemplateId;
        }

        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $typeId = $this->ensureType($technicalName, $now);
        return $this->ensureTemplate($typeId, $now);
    }

    private function fetchExistingTemplateId(string $technicalName): ?string
    {
        $row = $this->connection->fetchOne(
            'SELECT t.id FROM mail_template t
             JOIN mail_template_type mt ON mt.id = t.mail_template_type_id
             WHERE mt.technical_name = :technicalName
             ORDER BY t.system_default DESC, t.created_at ASC
             LIMIT 1',
            ['technicalName' => $technicalName]
        );

        return $row === false ? null : (string) $row;
    }

    private function ensureType(string $technicalName, string $now): string
    {
        $typeId = $this->connection->fetchOne(
            'SELECT id FROM mail_template_type WHERE technical_name = :technicalName',
            ['technicalName' => $technicalName]
        );

        if ($typeId !== false) {
            return (string) $typeId;
        }

        $newTypeId = Uuid::randomBytes();
        $availableEntities = $this->registry->getAvailableEntities($technicalName);

        try {
            $this->connection->insert('mail_template_type', [
                'id' => $newTypeId,
                'technical_name' => $technicalName,
                'available_entities' => $availableEntities !== []
                    ? json_encode($availableEntities, \JSON_THROW_ON_ERROR)
                    : null,
                'created_at' => $now,
            ]);
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            // Concurrent caller won the race; re-read.
            $typeId = $this->connection->fetchOne(
                'SELECT id FROM mail_template_type WHERE technical_name = :technicalName',
                ['technicalName' => $technicalName]
            );
            \assert($typeId !== false);

            return (string) $typeId;
        }

        $this->insertTypeTranslations($newTypeId, $technicalName, $now);

        return $newTypeId;
    }

    private function insertTypeTranslations(string $typeId, string $technicalName, string $now): void
    {
        $locales = $this->registry->getLocales($technicalName);
        if ($locales === []) {
            return;
        }

        /** @var array<string, string> $localeToLanguage locale code => binary language id */
        $localeToLanguage = $this->connection->fetchAllKeyValue(
            'SELECT locale.code, language.id
             FROM language
             INNER JOIN locale ON locale.id = language.locale_id
             WHERE locale.code IN (:codes)',
            ['codes' => $locales],
            ['codes' => ArrayParameterType::STRING]
        );

        foreach ($localeToLanguage as $locale => $languageId) {
            $default = $this->registry->getDefault($technicalName, $locale);
            if ($default === null) {
                continue;
            }

            $name = $this->registry->getTypeName($technicalName, $locale)
                ?? $this->registry->getTypeName($technicalName, 'en-GB')
                ?? $technicalName;

            try {
                $this->connection->insert('mail_template_type_translation', [
                    'mail_template_type_id' => $typeId,
                    'language_id' => $languageId,
                    'name' => $name,
                    'created_at' => $now,
                ]);
            } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
                // Concurrent caller already inserted this translation.
            }
        }
    }

    private function ensureTemplate(string $typeId, string $now): string
    {
        $templateId = $this->connection->fetchOne(
            'SELECT id FROM mail_template WHERE mail_template_type_id = :typeId
             ORDER BY system_default DESC, created_at ASC
             LIMIT 1',
            ['typeId' => $typeId]
        );

        if ($templateId !== false) {
            return (string) $templateId;
        }

        $newTemplateId = Uuid::randomBytes();

        $this->connection->insert('mail_template', [
            'id' => $newTemplateId,
            'mail_template_type_id' => $typeId,
            'system_default' => 1,
            'created_at' => $now,
        ]);

        return $newTemplateId;
    }
}
