<?php declare(strict_types=1);

namespace Shopware\Core\System\Mail\Aggregate\MailTranslation;

use Shopware\Core\Framework\ORM\EntityCollection;

class MailTranslationCollection extends EntityCollection
{
    /**
     * @var MailTranslationStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? MailTranslationStruct
    {
        return parent::get($id);
    }

    public function current(): MailTranslationStruct
    {
        return parent::current();
    }

    public function getMailIds(): array
    {
        return $this->fmap(function (MailTranslationStruct $mailTranslation) {
            return $mailTranslation->getMailId();
        });
    }

    public function filterByMailId(string $id): self
    {
        return $this->filter(function (MailTranslationStruct $mailTranslation) use ($id) {
            return $mailTranslation->getMailId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (MailTranslationStruct $mailTranslation) {
            return $mailTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (MailTranslationStruct $mailTranslation) use ($id) {
            return $mailTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return MailTranslationStruct::class;
    }
}
