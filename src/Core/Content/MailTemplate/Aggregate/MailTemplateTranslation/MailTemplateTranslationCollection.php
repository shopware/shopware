<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<MailTemplateTranslationEntity>
 */
#[Package('sales-channel')]
class MailTemplateTranslationCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getMailTemplateIds(): array
    {
        return $this->fmap(fn (MailTemplateTranslationEntity $mailTemplateTranslation) => $mailTemplateTranslation->getMailTemplateId());
    }

    public function filterByMailTemplateId(string $id): self
    {
        return $this->filter(fn (MailTemplateTranslationEntity $mailTemplateTranslation) => $mailTemplateTranslation->getMailTemplateId() === $id);
    }

    /**
     * @return list<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(fn (MailTemplateTranslationEntity $mailTemplateTranslation) => $mailTemplateTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(fn (MailTemplateTranslationEntity $mailTemplateTranslation) => $mailTemplateTranslation->getLanguageId() === $id);
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
