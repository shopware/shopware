<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                               add(MailTemplateTranslationEntity $entity)
 * @method void                               set(string $key, MailTemplateTranslationEntity $entity)
 * @method MailTemplateTranslationEntity[]    getIterator()
 * @method MailTemplateTranslationEntity[]    getElements()
 * @method MailTemplateTranslationEntity|null get(string $key)
 * @method MailTemplateTranslationEntity|null first()
 * @method MailTemplateTranslationEntity|null last()
 */
class MailTemplateTranslationCollection extends EntityCollection
{
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
