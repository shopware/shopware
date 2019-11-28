<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooterTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                                   add(MailHeaderFooterTranslationEntity $entity)
 * @method void                                   set(string $key, MailHeaderFooterTranslationEntity $entity)
 * @method MailHeaderFooterTranslationEntity[]    getIterator()
 * @method MailHeaderFooterTranslationEntity[]    getElements()
 * @method MailHeaderFooterTranslationEntity|null get(string $key)
 * @method MailHeaderFooterTranslationEntity|null first()
 * @method MailHeaderFooterTranslationEntity|null last()
 */
class MailHeaderFooterTranslationCollection extends EntityCollection
{
    public function getLanguageIds(): array
    {
        return $this->fmap(function (MailHeaderFooterTranslationEntity $mailTemplateTranslation) {
            return $mailTemplateTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (MailHeaderFooterTranslationEntity $mailTemplateTranslation) use ($id) {
            return $mailTemplateTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return MailHeaderFooterTranslationEntity::class;
    }
}
