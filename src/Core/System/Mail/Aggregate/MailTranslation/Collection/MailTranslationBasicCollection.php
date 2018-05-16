<?php declare(strict_types=1);

namespace Shopware\System\Mail\Aggregate\MailTranslation\Collection;

use Shopware\Framework\ORM\EntityCollection;
use Shopware\System\Mail\Aggregate\MailTranslation\Struct\MailTranslationBasicStruct;

class MailTranslationBasicCollection extends EntityCollection
{
    /**
     * @var MailTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? MailTranslationBasicStruct
    {
        return parent::get($id);
    }

    public function current(): MailTranslationBasicStruct
    {
        return parent::current();
    }

    public function getMailIds(): array
    {
        return $this->fmap(function (MailTranslationBasicStruct $mailTranslation) {
            return $mailTranslation->getMailId();
        });
    }

    public function filterByMailId(string $id): self
    {
        return $this->filter(function (MailTranslationBasicStruct $mailTranslation) use ($id) {
            return $mailTranslation->getMailId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (MailTranslationBasicStruct $mailTranslation) {
            return $mailTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (MailTranslationBasicStruct $mailTranslation) use ($id) {
            return $mailTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return MailTranslationBasicStruct::class;
    }
}
