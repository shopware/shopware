<?php declare(strict_types=1);

namespace Shopware\Mail\Collection;

use Shopware\Mail\Struct\MailTranslationDetailStruct;
use Shopware\Shop\Collection\ShopBasicCollection;

class MailTranslationDetailCollection extends MailTranslationBasicCollection
{
    /**
     * @var MailTranslationDetailStruct[]
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

    public function getLanguages(): ShopBasicCollection
    {
        return new ShopBasicCollection(
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
