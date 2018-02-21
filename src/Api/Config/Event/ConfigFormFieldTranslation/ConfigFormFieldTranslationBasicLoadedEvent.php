<?php declare(strict_types=1);

namespace Shopware\Api\Config\Event\ConfigFormFieldTranslation;

use Shopware\Api\Config\Collection\ConfigFormFieldTranslationBasicCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;

class ConfigFormFieldTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'config_form_field_translation.basic.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var ConfigFormFieldTranslationBasicCollection
     */
    protected $configFormFieldTranslations;

    public function __construct(ConfigFormFieldTranslationBasicCollection $configFormFieldTranslations, ShopContext $context)
    {
        $this->context = $context;
        $this->configFormFieldTranslations = $configFormFieldTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
    {
        return $this->context;
    }

    public function getConfigFormFieldTranslations(): ConfigFormFieldTranslationBasicCollection
    {
        return $this->configFormFieldTranslations;
    }
}
