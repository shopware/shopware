<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Log\Package;
use Twig\Compiler;
use Twig\Environment;
use Twig\Loader\LoaderInterface;
use Twig\Node\Node;

/**
 * @internal
 */
#[Package('framework')]
class TwigEnvironment extends Environment
{
    private ?Compiler $compiler = null;

    /**
     * @param array<mixed> $options
     */
    public function __construct(LoaderInterface $loader, array $options = [])
    {
        // There is no Symfony configuration yet to toggle this feature
        $options['use_yield'] = true;
        $options['debug'] = false;

        parent::__construct($loader, $options);
    }

    public function compile(Node $node): string
    {
        if ($this->compiler === null) {
            $this->compiler = new Compiler($this);
        }

        $source = $this->compiler->compile($node)->getSource();
        if ($this->shouldAddMacroResult($source)) {
            $source = $this->addMacroResult($source);
        }

        $replaces = [
            'CoreExtension::getAttribute(' => 'SwTwigFunction::getAttribute(',
            'twig_escape_filter(' => 'SwTwigFunction::escapeFilter(',
            'use Twig\Environment;' => "use Twig\Environment;\nuse Shopware\Core\Framework\Adapter\Twig\SwTwigFunction;",
        ];

        return str_replace(array_keys($replaces), array_values($replaces), $source);
    }

    private function shouldAddMacroResult(string $source): bool
    {
        return
            str_contains($source, 'getTemplateForMacro')
            && str_contains($source, 'SwTwigFunction::$macroResult =')
            && (!str_contains($source, 'CoreExtension::getAttribute') || str_contains($source, 'ComparisonExtension'))
        ;
    }

    private function addMacroResult(string $source): string
    {
        [$lines, $lineNumber] = $this->extractLinesAndNumber($source);
        if (isset($lineNumber)) {
            $callMacroResult = 'yield SwTwigFunction::$macroResult;';
            array_splice($lines, $lineNumber + 1, 0, $callMacroResult);
            $updatedSource = implode("\n", $lines);
        }

        return $updatedSource ?? $source;
    }

    /**
     * @return array{array<string>, int|null}
     */
    private function extractLinesAndNumber(string $source): array
    {
        $lines = explode("\n", $source);
        foreach ($lines as $index => $line) {
            if (str_contains($line, 'getTemplateForMacro')) {
                $lineNumber = $index;
                break;
            }
        }

        return [$lines, $lineNumber ?? null];
    }
}
