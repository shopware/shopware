<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution;

use Twig\Error\LoaderError;
use Twig\Loader\LoaderInterface;
use Twig\Source;

/**
 * @internal only for use by the app-system
 */
class ScriptTwigLoader implements LoaderInterface
{
    private Script $script;

    public function __construct(Script $script)
    {
        $this->script = $script;
    }

    public function getSourceContext(string $name): Source
    {
        $script = $this->get($name);

        if ($script === null) {
            throw new LoaderError(sprintf('Template "%s" is not defined.', $name));
        }

        return new Source($script->getScript(), $name);
    }

    public function getCacheKey(string $name): string
    {
        return $name;
    }

    public function isFresh(string $name, int $time): bool
    {
        $script = $this->get($name);

        if ($script === null) {
            return false;
        }

        return $script->getLastModified()->getTimestamp() < $time;
    }

    /**
     * @return bool
     */
    public function exists(string $name)
    {
        return $this->get($name) !== null;
    }

    private function get(string $name): ?Script
    {
        if ($this->script->getName() === $name) {
            return $this->script;
        }

        foreach ($this->script->getIncludes() as $include) {
            if ($include->getName() === $name) {
                return $include;
            }
        }

        return null;
    }
}
