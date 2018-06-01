<?php declare(strict_types=1);

namespace Shopware\System\Config\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Config\Collection\ConfigFormBasicCollection;

class ConfigFormBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'config_form.basic.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var ConfigFormBasicCollection
     */
    protected $configForms;

    public function __construct(ConfigFormBasicCollection $configForms, Context $context)
    {
        $this->context = $context;
        $this->configForms = $configForms;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getConfigForms(): ConfigFormBasicCollection
    {
        return $this->configForms;
    }
}
