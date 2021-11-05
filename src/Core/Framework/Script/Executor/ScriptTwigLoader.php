<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Executor;

use Shopware\Core\Framework\Script\ExecutableScript;
use Twig\Error\LoaderError;
use Twig\Loader\LoaderInterface;
use Twig\Source;

/**
 * @internal only for use by the app-system
 */
class ScriptTwigLoader implements LoaderInterface
{
    private ExecutableScript $script;

    public function __construct(ExecutableScript $script)
    {
        $this->script = $script;
    }

    public function getSourceContext(string $name): Source
    {
        if (!$this->exists($name)) {
            throw new LoaderError(sprintf('Template "%s" is not defined.', $name));
        }

        return new Source($this->script->getScript(), $name);
    }

    public function getCacheKey(string $name): string
    {
        return $name;
    }

    public function isFresh(string $name, int $time): bool
    {
        if (!$this->exists($name)) {
            return false;
        }

        return $this->script->getLastModified()->getTimestamp() < $time;
    }

    public function exists(string $name)
    {
        return $this->script->getName() === $name;
    }
}
