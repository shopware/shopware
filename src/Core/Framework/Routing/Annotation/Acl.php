<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Annotation;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\ConfigurationAnnotation;

/**
 * @Annotation
 */
class Acl extends ConfigurationAnnotation
{
    /** @var array */
    private $value;

    public function getAliasName()
    {
        return 'acl';
    }

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
