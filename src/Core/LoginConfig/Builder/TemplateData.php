<?php declare(strict_types=1);

namespace Shopware\Core\LoginConfig\Builder;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('core')]
class TemplateData
{
    public function __construct(
        public readonly string $key,
        public readonly string $snippetKey,
        public readonly string $icon,
        public readonly string $class,
        public readonly string $url,
    ) {
    }

    /**
     * @return array{key: string, snippet_key: string, icon: string, class: string, url: string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'snippet_key' => $this->snippetKey,
            'icon' => $this->icon,
            'class' => $this->class,
            'url' => $this->url,
        ];
    }
}
