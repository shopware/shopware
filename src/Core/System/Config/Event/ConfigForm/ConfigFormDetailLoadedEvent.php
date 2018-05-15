<?php declare(strict_types=1);

namespace Shopware\System\Config\Event\ConfigForm;

use Shopware\System\Config\Collection\ConfigFormDetailCollection;
use Shopware\System\Config\Event\ConfigFormField\ConfigFormFieldBasicLoadedEvent;
use Shopware\System\Config\Event\ConfigFormTranslation\ConfigFormTranslationBasicLoadedEvent;
use Shopware\Framework\Plugin\Event\Plugin\PluginBasicLoadedEvent;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ConfigFormDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'config_form.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ConfigFormDetailCollection
     */
    protected $configForms;

    public function __construct(ConfigFormDetailCollection $configForms, ApplicationContext $context)
    {
        $this->context = $context;
        $this->configForms = $configForms;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
