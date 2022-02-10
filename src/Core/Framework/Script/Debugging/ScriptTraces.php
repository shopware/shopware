<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Debugging;

use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * @deprecated tag:v6.5.0 will be internal
 */
class ScriptTraces extends AbstractDataCollector
{
    protected array $traces = [];

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->data = $this->traces;
    }

    public function init(string $hook): void
    {
        if (\array_key_exists($hook, $this->traces)) {
            // don't overwrite existing traces
            return;
        }

        $this->traces[$hook] = [];
    }

    public function trace(Hook $hook, Script $script, \Closure $execute): void
    {
        $time = microtime(true);

        $debug = new Debug();

        $execute($debug);

        $took = round(microtime(true) - $time, 3);

        $name = explode('/', $script->getName());
        $name = array_pop($name);

        $this->add($hook, $name, $took, $debug);
    }

    public function getHookCount(): int
    {
        if ($this->data instanceof Data) {
            return $this->data->count();
        }

        return \count($this->data);
    }

    public function getHooks(): array
    {
        if ($this->data instanceof Data) {
            return [];
        }

        return array_keys($this->data);
    }

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
            $count += \count($scripts);
        }

        return $count;
    }

    public static function getTemplate(): ?string
    {
        return 'storefront/profiling/script_traces.html.twig';
    }

    /**
     * @return array|Data
     */
    public function getData()
    {
        return $this->data;
    }

    public function getTraces(): array
    {
        return $this->traces;
    }

    private function add(Hook $hook, string $name, float $took, Debug $output): void
    {
        $this->traces[$hook->getName()][] = [
            'name' => $name,
            'took' => $took,
            'output' => $output->all(),
        ];
    }
}
