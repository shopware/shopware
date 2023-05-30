<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Debugging;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\FunctionHook;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('core')]
class ScriptTraces extends AbstractDataCollector implements ResetInterface
{
    /**
     * @var array<string, mixed>
     */
    protected array $traces = [];

    /**
     * @var list<string>
     */
    protected static array $deprecationNotices = [];

    public static function addDeprecationNotice(string $deprecationNotice): void
    {
        static::$deprecationNotices[] = $deprecationNotice;
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->data = $this->traces;
    }

    public function initHook(Hook $hook): void
    {
        $name = $this->getHookName($hook);

        if (\array_key_exists($name, $this->traces)) {
            // don't overwrite existing traces
            return;
        }

        $this->traces[$name] = [];
    }

    public function trace(Hook $hook, Script $script, \Closure $execute): void
    {
        $time = microtime(true);

        $debug = new Debug();

        static::$deprecationNotices = [];
        $execute($debug);
        $deprecations = static::$deprecationNotices;
        static::$deprecationNotices = [];

        $took = round(microtime(true) - $time, 3);

        $name = explode('/', (string) $script->getName());
        $name = array_pop($name);

        $this->add($hook, $name, $took, $debug, $deprecations);
    }

    public function getHookCount(): int
    {
        if ($this->data instanceof Data) {
            return $this->data->count();
        }

        return \count($this->data);
    }

    /**
     * @return list<string>
     */
    public function getHooks(): array
    {
        if ($this->data instanceof Data) {
            return [];
        }

        return array_keys($this->data);
    }

    /**
     * @return array<string, mixed>
     */
    public function getScripts(string $hook): array
    {
        return $this->data[$hook] ?? [];
    }

    public function getTook(): float
    {
        $data = $this->data instanceof Data ? $this->data->getIterator() : $this->data;

        $took = 0.0;
        foreach ($data as $scripts) {
            $took += array_sum(array_column($scripts, 'took'));
        }

        return $took;
    }

    public function getScriptCount(): int
    {
        $count = 0;
        foreach ($this->data as $scripts) {
            $count += is_countable($scripts) ? \count($scripts) : 0;
        }

        return $count;
    }

    public function getDeprecationCount(): int
    {
        $count = 0;
        foreach ($this->data as $scripts) {
            foreach ($scripts as $script) {
                foreach ($script['deprecations'] as $deprecationCount) {
                    $count += $deprecationCount;
                }
            }
        }

        return $count;
    }

    public static function getTemplate(): ?string
    {
        return '@Profiling/Collector/script_traces.html.twig';
    }

    /**
     * @return array<string, mixed>|Data
     */
    public function getData(): array|Data
    {
        return $this->data;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTraces(): array
    {
        return $this->traces;
    }

    /**
     * @internal
     *
     * @return array<string, mixed>
     */
    public function getOutput(string $name, int $index): array
    {
        return $this->traces[$name][$index]['output'];
    }

    public function reset(): void
    {
        parent::reset();
        $this->traces = [];
    }

    /**
     * @param list<string> $deprecations
     */
    private function add(Hook $hook, string $name, float $took, Debug $output, array $deprecations): void
    {
        $deprecations = array_count_values($deprecations);
        arsort($deprecations);

        $this->traces[$this->getHookName($hook)][] = [
            'name' => $name,
            'took' => $took,
            'output' => $output->all(),
            'deprecations' => $deprecations,
        ];
    }

    private function getHookName(Hook $hook): string
    {
        if (!$hook instanceof FunctionHook) {
            return $hook->getName();
        }

        return $hook->getName() . '::' . $hook->getFunctionName();
    }
}
