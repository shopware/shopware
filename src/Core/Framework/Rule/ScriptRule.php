<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\Adapter\Twig\Extension\ComparisonExtension;
use Shopware\Core\Framework\Adapter\Twig\Extension\PhpSyntaxExtension;
use Shopware\Core\Framework\Adapter\Twig\TwigEnvironment;
use Shopware\Core\Framework\App\Event\Hooks\AppScriptConditionHook;
use Shopware\Core\Framework\Script\Debugging\Debug;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Script\Exception\ScriptExecutionFailedException;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;
use Shopware\Core\Framework\Script\Execution\ScriptTwigLoader;
use Twig\Cache\FilesystemCache;
use Twig\Extension\DebugExtension;

/**
 * @package business-ops
 *
 * @internal
 */
class ScriptRule extends Rule
{
    protected string $script = '';

    protected array $constraints = [];

    protected array $values = [];

    protected ?\DateTimeInterface $lastModified = null;

    protected ?string $identifier = null;

    protected ?ScriptTraces $traces = null;

    protected ?string $cacheDir = null;

    protected bool $debug = true;

    public function match(RuleScope $scope): bool
    {
        $context = array_merge(['scope' => $scope], $this->values);
        $lastModified = $this->lastModified ?? $scope->getCurrentTime();
        $name = $this->identifier ?? $this->getName();

        $options = ['auto_reload' => true];
        if (!$this->debug) {
            $options['cache'] = new FilesystemCache($this->cacheDir . '/' . $name);
        } else {
            $options['debug'] = true;
        }

        $script = new Script(
            $name,
            sprintf('
                {%% apply spaceless %%}
                    {%% macro evaluate(%1$s) %%}
                        %2$s
                    {%% endmacro %%}

                    {%% set var = _self.evaluate(%1$s) %%}
                    {{ var }}
                {%% endapply  %%}
            ', implode(', ', array_keys($context)), $this->script),
            $lastModified,
            null,
            $options
        );

        $twig = new TwigEnvironment(
            new ScriptTwigLoader($script),
            $script->getTwigOptions()
        );

        $twig->addExtension(new PhpSyntaxExtension());
        $twig->addExtension(new ComparisonExtension());
        if ($this->debug) {
            $twig->addExtension(new DebugExtension());
        }

        $hook = new AppScriptConditionHook($scope->getContext());

        try {
            return $this->render($twig, $script, $hook, $name, $context);
        } catch (\Throwable $e) {
            throw new ScriptExecutionFailedException($hook->getName(), $script->getName(), $e);
        }
    }

    public function getConstraints(): array
    {
        return $this->constraints;
    }

    public function setConstraints(array $constraints): void
    {
        $this->constraints = $constraints;
    }

    public function getName(): string
    {
        return 'scriptRule';
    }

    private function render(TwigEnvironment $twig, Script $script, Hook $hook, string $name, array $context): bool
    {
        if (!$this->traces) {
            return filter_var(trim($twig->render($name, $context)), \FILTER_VALIDATE_BOOLEAN);
        }

        $match = false;
        $this->traces->trace($hook, $script, function (Debug $debug) use ($twig, $name, $context, &$match): void {
            $twig->addGlobal('debug', $debug);

            $match = filter_var(trim($twig->render($name, $context)), \FILTER_VALIDATE_BOOLEAN);

            $debug->dump($match, 'return');
        });

        return $match;
    }
}
