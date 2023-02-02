<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Event;

use Shopware\Core\Framework\Context;

class UpdatePostPrepareEvent extends UpdateEvent
{
    /**
     * @var string
     */
    private $currentVersion;

    /**
     * @var string
     */
    private $newVersion;

    public function __construct(Context $context, string $currentVersion, string $newVersion)
    {
        parent::__construct($context);
        $this->currentVersion = $currentVersion;
        $this->newVersion = $newVersion;
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
