<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use Shopware\Core\Framework\Log\Package;
use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;

/**
 * @internal
 */
#[Package('framework')]
final class TemplateContractContextExpression extends AbstractExpression
{
    public function __construct(AbstractExpression $variables, bool $withContext, string $template, string $type, int $line)
    {
        parent::__construct(['variables' => $variables], ['withContext' => $withContext, 'template' => $template, 'type' => $type], $line);
    }

    public function compile(Compiler $compiler): void
    {
        $compiler->raw('\\' . TemplateContracts::class . '::context($this->env, $context, ')
            ->subcompile($this->getNode('variables'))->raw(', ')
            ->repr($this->getAttribute('withContext'))->raw(', ')
            ->string($this->getAttribute('template'))->raw(', ')
            ->string($this->getAttribute('type'))->raw(')');
    }
}
