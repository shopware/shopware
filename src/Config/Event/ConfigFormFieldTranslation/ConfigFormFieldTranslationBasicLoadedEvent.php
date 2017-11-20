<?php declare(strict_types=1);

namespace Shopware\Config\Event\ConfigFormFieldTranslation;

use Shopware\Config\Collection\ConfigFormFieldTranslationBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class ConfigFormFieldTranslationBasicLoadedEvent extends NestedEvent
{
    const NAME = 'config_form_field_translation.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ConfigFormFieldTranslationBasicCollection
     */
    protected $configFormFieldTranslations;

    public function __construct(ConfigFormFieldTranslationBasicCollection $configFormFieldTranslations, TranslationContext $context)
    {
        $this->context = $context;
        $this->configFormFieldTranslations = $configFormFieldTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getConfigFormFieldTranslations(): ConfigFormFieldTranslationBasicCollection
    {
        return $this->configFormFieldTranslations;
    }
}
