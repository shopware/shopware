<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Annotation;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\ConfigurationInterface;
use Shopware\Core\Framework\Feature;

/**
 * @deprecated tag:v6.5.0 - Use route defaults with "_contextTokenRequired". Example: @Route(defaults={"_contextTokenRequired"=true)
 * @Annotation
 */
class ContextTokenRequired implements ConfigurationInterface
{
    /**
     * @var bool
     */
    protected $required = true;

    public function __construct(array $values)
    {
        $this->required = isset($values['value']) ? $values['value'] : true;
    }

    /**
     * @return string
     */
    public function getAliasName()
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', '"@Route(defaults={"_contextTokenRequired"=true)"')
        );

        return 'contextTokenRequired';
    }

    /**
     * @return bool
     */
    public function allowArray()
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', '"@Route(defaults={"_contextTokenRequired"=true)"')
        );

        return false;
    }

    public function isRequired(): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', '"@Route(defaults={"_contextTokenRequired"=true)"')
        );

        return $this->required;
    }

    public function setRequired(bool $required): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', '"@Route(defaults={"_contextTokenRequired"=true)"')
        );

        $this->required = $required;
    }
}
