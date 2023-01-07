<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Event;

use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\PluginEntity;

/**
 * @package core
 */
class PluginPostDeactivationFailedEvent extends PluginLifecycleEvent
{
    private ?\Throwable $exception;

    /**
     * @var ActivateContext
     */
    private $context;

    public function __construct(PluginEntity $plugin, ActivateContext $context, ?\Throwable $exception = null)
    {
        parent::__construct($plugin);
        $this->context = $context;
        $this->exception = $exception;
    }

    public function getContext(): ActivateContext
    {
        return $this->context;
    }

    public function getException(): ?\Throwable
    {
        return $this->exception;
    }
}
