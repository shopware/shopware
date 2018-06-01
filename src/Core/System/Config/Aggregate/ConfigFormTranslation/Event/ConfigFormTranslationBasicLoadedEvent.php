<?php declare(strict_types=1);

namespace Shopware\System\Config\Aggregate\ConfigFormTranslation\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Config\Aggregate\ConfigFormTranslation\Collection\ConfigFormTranslationBasicCollection;

class ConfigFormTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'config_form_translation.basic.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var ConfigFormTranslationBasicCollection
     */
    protected $configFormTranslations;

    public function __construct(ConfigFormTranslationBasicCollection $configFormTranslations, Context $context)
    {
        $this->context = $context;
        $this->configFormTranslations = $configFormTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getConfigFormTranslations(): ConfigFormTranslationBasicCollection
    {
        return $this->configFormTranslations;
    }
}
