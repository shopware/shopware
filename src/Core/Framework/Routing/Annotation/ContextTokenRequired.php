<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Annotation;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\ConfigurationInterface;

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
        return 'contextTokenRequired';
    }

    /**
     * @return bool
     */
    public function allowArray()
    {
        return false;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function setRequired(bool $required): void
    {
        $this->required = $required;
    }
}
