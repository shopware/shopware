<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Scaffolding;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class Stub
{
    public const TYPE_TEMPLATE = 'template';
    public const TYPE_RAW = 'raw';

    /**
     * @param array<string, string> $params
     */
    public function __construct(
        private readonly string $path,
        private string $content,
        private readonly string $type = self::TYPE_TEMPLATE,
        private readonly array $params = [],
    ) {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getContent(): ?string
    {
        $content = $this->content;

        if ($this->type === self::TYPE_TEMPLATE) {
            $content = file_get_contents($this->content);
        }

        if ($content === false) {
            return null;
        }

        return str_replace(
            array_map(static fn (string $param) => '{{ ' . $param . ' }}', array_keys($this->params)),
            array_values($this->params),
            $content
        );
    }

    /**
     * @param array<string, string> $params
     */
    public static function template(string $destinationPath, string $sourcePath, array $params = []): self
    {
        return new self($destinationPath, $sourcePath, self::TYPE_TEMPLATE, $params);
    }

    /**
     * @param array<string, string> $params
     */
    public static function raw(string $destinationPath, string $content, array $params = []): self
    {
        return new self($destinationPath, $content, self::TYPE_RAW, $params);
    }
}
