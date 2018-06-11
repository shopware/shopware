<?php declare(strict_types=1);

namespace Shopware\Core\System\Config\Aggregate\ConfigFormField\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\Config\Aggregate\ConfigFormField\Collection\ConfigFormFieldBasicCollection;

class ConfigFormFieldBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'config_form_field.basic.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var ConfigFormFieldBasicCollection
     */
    protected $configFormFields;

    public function __construct(ConfigFormFieldBasicCollection $configFormFields, Context $context)
    {
        $this->context = $context;
        $this->configFormFields = $configFormFields;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getConfigFormFields(): ConfigFormFieldBasicCollection
    {
        return $this->configFormFields;
    }
}
