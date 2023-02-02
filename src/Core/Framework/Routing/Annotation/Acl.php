<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Annotation;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\ConfigurationAnnotation;
use Shopware\Core\Framework\Feature;

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
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', '"@Route(defaults={"_acl"={"product:read"})"')
        );

        return 'acl';
    }

    /**
     * @return bool
     */
    public function allowArray()
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', '"@Route(defaults={"_acl"={"product:read"})"')
        );

        return false;
    }

    public function getValue(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', '"@Route(defaults={"_acl"={"product:read"})"')
        );

        return $this->value;
    }

    public function setValue(array $privileges): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', '"@Route(defaults={"_acl"={"product:read"})"')
        );

        $this->value = $privileges;
    }
}
