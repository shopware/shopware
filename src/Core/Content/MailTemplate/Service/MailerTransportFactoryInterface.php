<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

interface MailerTransportFactoryInterface
{
    public function create(SystemConfigService $configService, \Swift_Transport $innerTransport): \Swift_Transport;
}
