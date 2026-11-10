<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use Shopware\Core\Framework\Log\Package;
use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;

/**
 * @internal
 */
#[Package('framework')]
final class TemplateNamesExpression extends AbstractExpression
{
    /**
     * @param non-empty-list<string> $templates
     */
    public function __construct(array $templates, int $line)
    {
        parent::__construct([], ['templates' => $templates], $line);
    }

    public function compile(Compiler $compiler): void
    {
        // PHPStan infers the union of array keys without branching through separate include calls.
        $compiler->raw('\\array_rand(')->repr(array_fill_keys($this->getAttribute('templates'), true))->raw(')');
    }
}
