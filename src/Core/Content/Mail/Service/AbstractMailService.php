<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Service;

use Shopware\Core\Framework\Context;
use Symfony\Component\Mime\Email;

abstract class AbstractMailService
{
    abstract public function getDecorated(): AbstractMailService;

    abstract public function send(array $data, Context $context, array $templateData = []): ?Email;
}
