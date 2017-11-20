<?php declare(strict_types=1);

namespace Shopware\Mail\Struct;

use Shopware\Shop\Struct\ShopBasicStruct;

class MailTranslationDetailStruct extends MailTranslationBasicStruct
{
    /**
     * @var MailBasicStruct
     */
    protected $mail;

    /**
     * @var ShopBasicStruct
     */
    protected $language;

    public function getMail(): MailBasicStruct
    {
        return $this->mail;
    }

    public function setMail(MailBasicStruct $mail): void
    {
        $this->mail = $mail;
    }

    public function getLanguage(): ShopBasicStruct
    {
        return $this->language;
    }

    public function setLanguage(ShopBasicStruct $language): void
    {
        $this->language = $language;
    }
}
