<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooterTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

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
