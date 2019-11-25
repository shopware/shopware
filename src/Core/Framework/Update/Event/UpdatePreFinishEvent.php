<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Event;

use Shopware\Core\Framework\Context;

class UpdatePreFinishEvent extends UpdateEvent
{
    /**
     * @var string
     */
    private $oldVersion;

    /**
     * @var string
     */
    private $newVersion;

    public function __construct(Context $context, string $oldVersion, string $newVersion)
    {
        parent::__construct($context);
        $this->oldVersion = $oldVersion;
        $this->newVersion = $newVersion;
    }

    public function getOldVersion(): string
    {
        return $this->oldVersion;
    }

    public function getNewVersion(): string
    {
        return $this->newVersion;
    }
}
