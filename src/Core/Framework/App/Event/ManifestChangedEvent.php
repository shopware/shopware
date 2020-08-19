<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;

abstract class ManifestChangedEvent extends AppChangedEvent
{
    /**
     * @var Manifest
     */
    private $app;

    public function __construct(string $appId, Manifest $app, Context $context)
    {
        $this->app = $app;
        parent::__construct($appId, $context);
    }

    abstract public function getName(): string;

    public function getApp(): Manifest
    {
        return $this->app;
    }
}
