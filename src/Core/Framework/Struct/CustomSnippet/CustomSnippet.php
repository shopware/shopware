<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct\CustomSnippet;

use Shopware\Core\Framework\Struct\Struct;

final class CustomSnippet extends Struct
{
    public const SNIPPET_TYPE = 'snippet';
    public const PLAIN_TYPE = 'plain';

    protected string $type;

    protected string $value;

    private function __construct(string $type, string $value)
    {
        $this->type = $type;
        $this->value = $value;
    }

    public static function createSnippet(string $value): self
    {
        return new self(self::SNIPPET_TYPE, $value);
    }

    public static function createPlain(string $value): self
    {
        return new self(self::PLAIN_TYPE, $value);
    }

    public function getApiAlias(): string
    {
        return 'custom_snippet';
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
