<?php declare(strict_types=1);

namespace Shopware\Core\System\Mail\Aggregate\MailTranslation\Collection;

use Shopware\Core\System\Language\Collection\LanguageBasicCollection;
use Shopware\Core\System\Mail\Aggregate\MailTranslation\Struct\MailTranslationDetailStruct;
use Shopware\Core\System\Mail\Collection\MailBasicCollection;

class MailTranslationDetailCollection extends MailTranslationBasicCollection
{
    /**
     * @var \Shopware\Core\System\Mail\Aggregate\MailTranslation\Struct\MailTranslationDetailStruct[]
     */
    protected $elements = [];

    public function getMails(): MailBasicCollection
    {
        return new MailBasicCollection(
            $this->fmap(function (MailTranslationDetailStruct $mailTranslation) {
                return $mailTranslation->getMail();
            })
        );
    }

    public function getLanguages(): LanguageBasicCollection
    {
        return new LanguageBasicCollection(
            $this->fmap(function (MailTranslationDetailStruct $mailTranslation) {
                return $mailTranslation->getLanguage();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return MailTranslationDetailStruct::class;
    }
}
