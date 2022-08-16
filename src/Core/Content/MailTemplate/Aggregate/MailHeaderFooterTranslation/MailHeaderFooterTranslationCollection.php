<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooterTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<MailHeaderFooterTranslationEntity>
 */
class MailHeaderFooterTranslationCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
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

    public function getApiAlias(): string
    {
        return 'mail_template_header_footer_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return MailHeaderFooterTranslationEntity::class;
    }
}
