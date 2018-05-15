<?php declare(strict_types=1);

namespace Shopware\System\Config\Event\ConfigFormTranslation;

use Shopware\System\Config\Collection\ConfigFormTranslationBasicCollection;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ConfigFormTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'config_form_translation.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ConfigFormTranslationBasicCollection
     */
    protected $configFormTranslations;

    public function __construct(ConfigFormTranslationBasicCollection $configFormTranslations, ApplicationContext $context)
    {
        $this->context = $context;
        $this->configFormTranslations = $configFormTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getConfigFormTranslations(): ConfigFormTranslationBasicCollection
    {
        return $this->configFormTranslations;
    }
}
