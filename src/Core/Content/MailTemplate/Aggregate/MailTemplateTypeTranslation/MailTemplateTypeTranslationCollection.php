<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateTypeTranslation;

use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                          add(ProductTranslationEntity $entity)
 * @method void                          set(string $key, ProductTranslationEntity $entity)
 * @method ProductTranslationEntity[]    getIterator()
 * @method ProductTranslationEntity[]    getElements()
 * @method ProductTranslationEntity|null get(string $key)
 * @method ProductTranslationEntity|null first()
 * @method ProductTranslationEntity|null last()
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
