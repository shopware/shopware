<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate;

class MailTemplateTypes
{
    public const NEWSLETTER = 'newsletter';

    public const NEWSLETTER_DO_CONFIRM = 'newsletter_do_confirm'; // after subscription with confirm instructions

    public const NEWSLETTER_CONFIRMED = 'newsletter_confirmed'; // after confirmation is done

    public const DELIVERY_NOTE = 'delivery_mail';

    public const INVOICE = 'invoice_mail';

    public const CREDIT_NOTE = 'credit_note_mail';

    public const STORNO = 'storno_mail';
}
