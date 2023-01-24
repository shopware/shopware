<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooterTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<MailHeaderFooterTranslationEntity>
 *
 * @package sales-channel
 */
class MailHeaderFooterTranslationCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(fn (MailHeaderFooterTranslationEntity $mailTemplateTranslation) => $mailTemplateTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(fn (MailHeaderFooterTranslationEntity $mailTemplateTranslation) => $mailTemplateTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'mail_template_header_footer_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return MailHeaderFooterTranslationEntity::class;
    }
}
