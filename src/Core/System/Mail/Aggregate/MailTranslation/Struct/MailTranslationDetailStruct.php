<?php declare(strict_types=1);

namespace Shopware\Core\System\Mail\Aggregate\MailTranslation\Struct;

use Shopware\Core\System\Language\Struct\LanguageBasicStruct;
use Shopware\Core\System\Mail\Struct\MailBasicStruct;

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
