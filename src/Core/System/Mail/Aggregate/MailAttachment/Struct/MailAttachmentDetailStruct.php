<?php declare(strict_types=1);

namespace Shopware\System\Mail\Aggregate\MailAttachment\Struct;

use Shopware\Content\Media\Struct\MediaBasicStruct;
use Shopware\System\Mail\Struct\MailBasicStruct;

class MailAttachmentDetailStruct extends MailAttachmentBasicStruct
{
    /**
     * @var MailBasicStruct
     */
    protected $mail;

    /**
     * @var MediaBasicStruct
     */
    protected $media;

    public function getMail(): MailBasicStruct
    {
        return $this->mail;
    }

    public function setMail(MailBasicStruct $mail): void
    {
        $this->mail = $mail;
    }

    public function getMedia(): MediaBasicStruct
    {
        return $this->media;
    }

    public function setMedia(MediaBasicStruct $media): void
    {
        $this->media = $media;
    }
}
