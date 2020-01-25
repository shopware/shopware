<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

interface MailerFactoryInterface
{
    public function create(SystemConfigService $configService, \Swift_Mailer $mailer): \Swift_Mailer;
}
