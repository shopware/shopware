<?php declare(strict_types=1);

namespace Shopware\Core\System\Config\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Plugin\Event\PluginBasicLoadedEvent;
use Shopware\Core\System\Config\Aggregate\ConfigFormField\Event\ConfigFormFieldBasicLoadedEvent;
use Shopware\Core\System\Config\Aggregate\ConfigFormTranslation\Event\ConfigFormTranslationBasicLoadedEvent;
use Shopware\Core\System\Config\Collection\ConfigFormDetailCollection;

class ConfigFormDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'config_form.detail.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var ConfigFormDetailCollection
     */
    protected $configForms;

    public function __construct(ConfigFormDetailCollection $configForms, Context $context)
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

    public function getConfigForms(): ConfigFormDetailCollection
    {
        return $this->configForms;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->configForms->getParents()->count() > 0) {
            $events[] = new ConfigFormBasicLoadedEvent($this->configForms->getParents(), $this->context);
        }
        if ($this->configForms->getPlugins()->count() > 0) {
            $events[] = new PluginBasicLoadedEvent($this->configForms->getPlugins(), $this->context);
        }
        if ($this->configForms->getChildren()->count() > 0) {
            $events[] = new ConfigFormBasicLoadedEvent($this->configForms->getChildren(), $this->context);
        }
        if ($this->configForms->getFields()->count() > 0) {
            $events[] = new ConfigFormFieldBasicLoadedEvent($this->configForms->getFields(), $this->context);
        }
        if ($this->configForms->getTranslations()->count() > 0) {
            $events[] = new ConfigFormTranslationBasicLoadedEvent($this->configForms->getTranslations(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
