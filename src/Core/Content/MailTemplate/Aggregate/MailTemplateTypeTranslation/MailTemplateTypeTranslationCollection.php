<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateTypeTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<MailTemplateTypeTranslationEntity>
 */
class MailTemplateTypeTranslationCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getMailTemplateIds(): array
    {
        return $this->fmap(function (MailTemplateTypeTranslationEntity $mailTemplateTypeTranslation) {
            return $mailTemplateTypeTranslation->getMailTemplateTypeId();
        });
    }

    public function filterByMailTemplateId(string $id): self
    {
        return $this->filter(function (MailTemplateTypeTranslationEntity $mailTemplateTypeTranslation) use ($id) {
            return $mailTemplateTypeTranslation->getMailTemplateTypeId() === $id;
        });
    }

    /**
     * @return list<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(function (MailTemplateTypeTranslationEntity $mailTemplateTypeTranslation) {
            return $mailTemplateTypeTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (MailTemplateTypeTranslationEntity $mailTemplateTypeTranslation) use ($id) {
            return $mailTemplateTypeTranslation->getLanguageId() === $id;
        });
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
