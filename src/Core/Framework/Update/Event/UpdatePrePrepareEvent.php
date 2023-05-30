<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

#[Package('system-settings')]
class UpdatePrePrepareEvent extends UpdateEvent
{
    public function __construct(
        Context $context,
        private readonly string $currentVersion,
        private readonly string $newVersion
    ) {
        parent::__construct($context);
    }

    public function getCurrentVersion(): string
    {
        return $this->currentVersion;
    }

    public function getNewVersion(): string
    {
        return $this->newVersion;
    }
}
