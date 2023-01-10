<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Annotation;

/**
 * @Annotation
 *
 * @package core
 */
class Since extends BaseAnnotation
{
    private string $value;

    public function getAliasName(): string
    {
        return 'since';
    }

    public function allowArray(): bool
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
