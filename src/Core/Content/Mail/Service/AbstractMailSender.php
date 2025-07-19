<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Service;

use Shopware\Core\Content\Mail\MailException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Mime\Email;

#[Package('after-sales')]
abstract class AbstractMailSender
{
    abstract public function getDecorated(): AbstractMailSender;

    /**
     * @throws MailException
     */
    abstract public function send(Email $email): void;
}
