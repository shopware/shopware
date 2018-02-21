<?php declare(strict_types=1);

namespace Shopware\Api\Config\Event\ConfigForm;

use Shopware\Api\Config\Collection\ConfigFormBasicCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;

class ConfigFormBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'config_form.basic.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var ConfigFormBasicCollection
     */
    protected $configForms;

    public function __construct(ConfigFormBasicCollection $configForms, ShopContext $context)
    {
        $this->context = $context;
        $this->configForms = $configForms;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
    {
        return $this->context;
    }

    public function getConfigForms(): ConfigFormBasicCollection
    {
        return $this->configForms;
    }
}
