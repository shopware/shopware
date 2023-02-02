<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<MailTemplateTranslationEntity>
 */
class MailTemplateTranslationCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getMailTemplateIds(): array
    {
        return $this->fmap(function (MailTemplateTranslationEntity $mailTemplateTranslation) {
            return $mailTemplateTranslation->getMailTemplateId();
        });
    }

    public function filterByMailTemplateId(string $id): self
    {
        return $this->filter(function (MailTemplateTranslationEntity $mailTemplateTranslation) use ($id) {
            return $mailTemplateTranslation->getMailTemplateId() === $id;
        });
    }

    /**
     * @return list<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(function (MailTemplateTranslationEntity $mailTemplateTranslation) {
            return $mailTemplateTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (MailTemplateTranslationEntity $mailTemplateTranslation) use ($id) {
            return $mailTemplateTranslation->getLanguageId() === $id;
        });
    }

    public function getApiAlias(): string
    {
        return 'mail_template_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return MailTemplateTranslationEntity::class;
    }
}
