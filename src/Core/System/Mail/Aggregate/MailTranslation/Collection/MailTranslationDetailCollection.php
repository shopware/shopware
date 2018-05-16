<?php declare(strict_types=1);

namespace Shopware\System\Mail\Aggregate\MailTranslation\Collection;

use Shopware\Application\Language\Collection\LanguageBasicCollection;
use Shopware\System\Mail\Aggregate\MailTranslation\Collection\MailTranslationBasicCollection;
use Shopware\System\Mail\Collection\MailBasicCollection;
use Shopware\System\Mail\Aggregate\MailTranslation\Struct\MailTranslationDetailStruct;

class MailTranslationDetailCollection extends MailTranslationBasicCollection
{
    /**
     * @var \Shopware\System\Mail\Aggregate\MailTranslation\Struct\MailTranslationDetailStruct[]
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
