<?php declare(strict_types=1);

namespace Shopware\System\Config\Event\ConfigFormFieldValue;

use Shopware\System\Config\Collection\ConfigFormFieldValueBasicCollection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ConfigFormFieldValueBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'config_form_field_value.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ConfigFormFieldValueBasicCollection
     */
    protected $configFormFieldValues;

    public function __construct(ConfigFormFieldValueBasicCollection $configFormFieldValues, ApplicationContext $context)
    {
        $this->context = $context;
        $this->configFormFieldValues = $configFormFieldValues;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getConfigFormFieldValues(): ConfigFormFieldValueBasicCollection
    {
        return $this->configFormFieldValues;
    }
}
