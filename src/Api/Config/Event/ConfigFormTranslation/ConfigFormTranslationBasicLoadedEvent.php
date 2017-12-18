<?php declare(strict_types=1);

namespace Shopware\Api\Config\Event\ConfigFormTranslation;

use Shopware\Api\Config\Collection\ConfigFormTranslationBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class ConfigFormTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'config_form_translation.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ConfigFormTranslationBasicCollection
     */
    protected $configFormTranslations;

    public function __construct(ConfigFormTranslationBasicCollection $configFormTranslations, TranslationContext $context)
    {
        $this->context = $context;
        $this->configFormTranslations = $configFormTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getConfigFormTranslations(): ConfigFormTranslationBasicCollection
    {
        return $this->configFormTranslations;
    }
}
