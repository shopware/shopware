<?php declare(strict_types=1);

namespace Shopware\System\Mail\Aggregate\MailTranslation\Struct;

use Shopware\Application\Language\Struct\LanguageBasicStruct;

use Shopware\System\Mail\Struct\MailBasicStruct;

class MailTranslationDetailStruct extends MailTranslationBasicStruct
{
    /**
     * @var MailBasicStruct
     */
    protected $mail;

    /**
     * @var LanguageBasicStruct
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

    public function getLanguage(): LanguageBasicStruct
    {
        return $this->language;
    }

    public function setLanguage(LanguageBasicStruct $language): void
    {
        $this->language = $language;
    }
}
