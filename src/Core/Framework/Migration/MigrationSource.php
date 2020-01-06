<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

class MigrationSource
{
    private const PHP_CLASS_NAME_REGEX = '[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$';

    /**
     * @var string
     */
    private $name;

    /**
     * @var array<string, string>
     */
    private $sources = [];

    public function __construct(string $name, iterable $namespaces = [])
    {
        $this->name = $name;

        foreach ($namespaces as $directory => $namespace) {
            $this->addDirectory($directory, $namespace);
        }
    }

    public function addDirectory(string $directory, string $namespace): void
    {
        $this->sources[$directory] = $namespace;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSourceDirectories(): array
    {
        return $this->sources;
    }

    public function getNamespacePattern(): string
    {
        $patterns = [];

        foreach ($this->sources as $namespace) {
            $patterns[] = '^' . str_ireplace('\\', '\\\\', $namespace) . '\\\\' . self::PHP_CLASS_NAME_REGEX;
        }

        if (count($patterns) === 1) {
            return $patterns[0];
        }

        return '(' . implode('|', $patterns) . ')';
    }
}
