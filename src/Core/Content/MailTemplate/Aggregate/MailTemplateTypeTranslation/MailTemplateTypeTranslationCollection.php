<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateTypeTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<MailTemplateTypeTranslationEntity>
 */
#[Package('services-settings')]
class MailTemplateTypeTranslationCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getMailTemplateIds(): array
    {
        return $this->fmap(fn (MailTemplateTypeTranslationEntity $mailTemplateTypeTranslation) => $mailTemplateTypeTranslation->getMailTemplateTypeId());
    }

    public function filterByMailTemplateId(string $id): self
    {
        return $this->filter(fn (MailTemplateTypeTranslationEntity $mailTemplateTypeTranslation) => $mailTemplateTypeTranslation->getMailTemplateTypeId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(fn (MailTemplateTypeTranslationEntity $mailTemplateTypeTranslation) => $mailTemplateTypeTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(fn (MailTemplateTypeTranslationEntity $mailTemplateTypeTranslation) => $mailTemplateTypeTranslation->getLanguageId() === $id);
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
