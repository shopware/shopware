<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateTypeTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<MailTemplateTypeTranslationEntity>
 */
#[Package('after-sales')]
class MailTemplateTypeTranslationCollection extends EntityCollection
{
    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     *
     * @return array<string>
     */
    public function getMailTemplateIds(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return $this->fmap(static fn (MailTemplateTypeTranslationEntity $mailTemplateTypeTranslation) => $mailTemplateTypeTranslation->getMailTemplateTypeId());
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     */
    public function filterByMailTemplateId(string $id): self
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return $this->filter(static fn (MailTemplateTypeTranslationEntity $mailTemplateTypeTranslation) => $mailTemplateTypeTranslation->getMailTemplateTypeId() === $id);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     *
     * @return array<string>
     */
    public function getLanguageIds(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return $this->fmap(static fn (MailTemplateTypeTranslationEntity $mailTemplateTypeTranslation) => $mailTemplateTypeTranslation->getLanguageId());
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     */
    public function filterByLanguageId(string $id): self
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return $this->filter(static fn (MailTemplateTypeTranslationEntity $mailTemplateTypeTranslation) => $mailTemplateTypeTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'mail_template_type_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return MailTemplateTypeTranslationEntity::class;
    }
}
