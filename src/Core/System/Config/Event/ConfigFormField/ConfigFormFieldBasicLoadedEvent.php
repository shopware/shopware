<?php declare(strict_types=1);

namespace Shopware\System\Config\Event\ConfigFormField;

use Shopware\System\Config\Collection\ConfigFormFieldBasicCollection;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ConfigFormFieldBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'config_form_field.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ConfigFormFieldBasicCollection
     */
    protected $configFormFields;

    public function __construct(ConfigFormFieldBasicCollection $configFormFields, ApplicationContext $context)
    {
        $this->context = $context;
        $this->configFormFields = $configFormFields;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getConfigFormFields(): ConfigFormFieldBasicCollection
    {
        return $this->configFormFields;
    }
}
