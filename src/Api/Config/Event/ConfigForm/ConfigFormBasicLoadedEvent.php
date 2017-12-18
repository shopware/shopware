<?php declare(strict_types=1);

namespace Shopware\Api\Config\Event\ConfigForm;

use Shopware\Api\Config\Collection\ConfigFormBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class ConfigFormBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'config_form.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ConfigFormBasicCollection
     */
    protected $configForms;

    public function __construct(ConfigFormBasicCollection $configForms, TranslationContext $context)
    {
        $this->context = $context;
        $this->configForms = $configForms;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getConfigForms(): ConfigFormBasicCollection
    {
        return $this->configForms;
    }
}
