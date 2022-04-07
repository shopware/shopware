<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Annotation;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\ConfigurationAnnotation;

/**
 * @deprecated tag:v6.5.0 - Use route defaults with "_acl". Example: @Route(defaults={"_acl"={"product:read"})
 * @Annotation
 */
class Acl extends ConfigurationAnnotation
{
    /**
     * @var array
     */
    private $value;

    /**
     * @return string
     */
    public function getAliasName()
    {
        return 'acl';
    }

    /**
     * @return bool
     */
    public function allowArray()
    {
        return false;
    }

    public function getValue(): array
    {
        return $this->value;
    }

    public function setValue(array $privileges): void
    {
        $this->value = $privileges;
    }
}
