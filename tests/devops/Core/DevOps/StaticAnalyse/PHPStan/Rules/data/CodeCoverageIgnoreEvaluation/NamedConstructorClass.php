<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

/**
 * @codeCoverageIgnore
 */
class NamedConstructorClass
{
    protected string $root;

    public static function create(string $root): self
    {
        $self = new self();
        $self->root = $root;

        return $self;
    }
}
