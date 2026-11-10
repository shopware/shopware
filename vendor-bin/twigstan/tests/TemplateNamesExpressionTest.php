<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Twig\Compiler;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('framework')]
class TemplateNamesExpressionTest extends TestCase
{
    public function testCompilesCandidateNamesAsArrayKeys(): void
    {
        $expression = new TemplateNamesExpression(['@Storefront/first.html.twig', '@Storefront/second.html.twig'], 12);
        $compiler = new Compiler(new Environment(new ArrayLoader()));

        $compiled = $compiler->compile($expression)->getSource();

        static::assertStringStartsWith('\\array_rand(', $compiled);
        static::assertStringContainsString('"@Storefront/first.html.twig" => true', $compiled);
        static::assertStringContainsString('"@Storefront/second.html.twig" => true', $compiled);
        static::assertSame(12, $expression->getTemplateLine());
    }
}
