<?php declare(strict_types=1);

namespace Shopware\Core\System\Config\Aggregate\ConfigFormFieldTranslation\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\Config\Aggregate\ConfigFormFieldTranslation\Collection\ConfigFormFieldTranslationBasicCollection;

class ConfigFormFieldTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'config_form_field_translation.basic.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var \Shopware\Core\System\Config\Aggregate\ConfigFormFieldTranslation\Collection\ConfigFormFieldTranslationBasicCollection
     */
    protected $configFormFieldTranslations;

    public function __construct(ConfigFormFieldTranslationBasicCollection $configFormFieldTranslations, Context $context)
    {
        $this->context = $context;
        $this->configFormFieldTranslations = $configFormFieldTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getConfigFormFieldTranslations(): ConfigFormFieldTranslationBasicCollection
    {
        return $this->configFormFieldTranslations;
    }
}
