<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Snippet\Mock;

use Shopware\Core\System\Snippet\Files\AbstractSnippetFile;

/**
 * @internal
 *
 * @package system-settings
 */
class MockSnippetFile extends AbstractSnippetFile
{
    private string $name;

    private bool $isBase;

    private string $iso;

    private string $technicalName;

    public function __construct(string $name, ?string $iso = null, string $content = '{}', bool $isBase = true, string $technicalName = 'mock')
    {
        $this->name = $name;
        $this->iso = $iso ?? $name;
        $this->isBase = $isBase;
        file_put_contents($this->getPath(), $content);
        $this->technicalName = $technicalName;
    }

    public static function cleanup(): void
    {
        foreach (glob(__DIR__ . '/_fixtures/*.json') ?: [] as $mockFile) {
            unlink($mockFile);
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPath(): string
    {
        return sprintf('%s/_fixtures/%s.json', __DIR__, $this->getName());
    }

    public function getIso(): string
    {
        return $this->iso;
    }

    public function getAuthor(): string
    {
        return $this->name;
    }

    public function isBase(): bool
    {
        return $this->isBase;
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }
}
