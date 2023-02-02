<?php declare(strict_types=1);

namespace Shopware\Docs\Inspection;

class ArrayWriter
{
    private array $data = [];

    /**
     * @var string
     */
    private $path;

    public function __construct(string $fromPath)
    {
        if (file_exists($fromPath)) {
            $this->data = (array) require $fromPath;
        }
        $this->path = $fromPath;
    }

    public function get(string $key): string
    {
        if (!isset($this->data[$key])) {
            throw new \InvalidArgumentException('Unable to find key ' . $key);
        }

        return $this->data[$key];
    }

    public function ensure(string $key): void
    {
        if (isset($this->data[$key])) {
            return;
        }

        $this->data[$key] = '__EMPTY__';
    }

    public function set(string $name, string $value): void
    {
        $this->data[$name] = $value;
    }

    public function dump(bool $long = false): void
    {
        $content = '<?php declare(strict_types=1);' . \PHP_EOL . \PHP_EOL;
        $content .= 'return [' . \PHP_EOL;

        if ($long) {
            $content .= $this->dumpDataLong();
        } else {
            $content .= $this->dumpDataShort();
        }

        $content .= '];' . \PHP_EOL;

        file_put_contents($this->path, $content);
    }

    private function dumpDataShort(): string
    {
        $content = '';

        foreach ($this->data as $key => $value) {
            $content .= '    ' . $this->formatKey($key) . ' => ' . var_export($value, true) . ',' . \PHP_EOL;
        }

        return $content;
    }

    private function dumpDataLong(): string
    {
        $content = '';

        foreach ($this->data as $key => $value) {
            if ($value === '') {
                $content .= '    ' . $this->formatKey($key) . ' => ' . var_export($value, true) . ',' . \PHP_EOL;
            } else {
                $content .= '    ' . $this->formatKey($key) . ' => <<<\'EOD\'' . \PHP_EOL . $value . \PHP_EOL . 'EOD' . \PHP_EOL . '    ,' . \PHP_EOL;
            }
        }

        return $content;
    }

    private function formatKey(string $key): string
    {
        if (class_exists($key)) {
            return $key . '::class';
        }

        return var_export($key, true);
    }
}
