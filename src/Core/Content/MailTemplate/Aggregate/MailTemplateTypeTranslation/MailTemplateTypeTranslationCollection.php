<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateTypeTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                                   add(MailTemplateTypeTranslationEntity $entity)
 * @method void                                   set(string $key, MailTemplateTypeTranslationEntity $entity)
 * @method MailTemplateTypeTranslationEntity[]    getIterator()
 * @method MailTemplateTypeTranslationEntity[]    getElements()
 * @method MailTemplateTypeTranslationEntity|null get(string $key)
 * @method MailTemplateTypeTranslationEntity|null first()
 * @method MailTemplateTypeTranslationEntity|null last()
 */
class MailTemplateTypeTranslationCollection extends EntityCollection
{
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

    protected function getExpectedClass(): string
    {
        return MailTemplateTypeTranslationEntity::class;
    }
}
