<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Annotation;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\ConfigurationAnnotation;

/**
 * @Annotation
 */
class Entity extends ConfigurationAnnotation
{
    /** @var string */
    private $value;

    public function getAliasName()
    {
        return 'entity';
    }

    public function allowArray()
    {
        return false;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $entity): void
    {
        $this->value = $entity;
    }
}
