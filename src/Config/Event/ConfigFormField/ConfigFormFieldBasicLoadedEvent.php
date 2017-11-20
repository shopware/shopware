<?php declare(strict_types=1);

namespace Shopware\Config\Event\ConfigFormField;

use Shopware\Config\Collection\ConfigFormFieldBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class ConfigFormFieldBasicLoadedEvent extends NestedEvent
{
    const NAME = 'config_form_field.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ConfigFormFieldBasicCollection
     */
    protected $configFormFields;

    public function __construct(ConfigFormFieldBasicCollection $configFormFields, TranslationContext $context)
    {
        $this->context = $context;
        $this->configFormFields = $configFormFields;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getConfigFormFields(): ConfigFormFieldBasicCollection
    {
        return $this->configFormFields;
    }
}
