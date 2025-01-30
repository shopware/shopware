<?php declare(strict_types=1);

namespace Shopware\Administration\Login\Config;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
final class TemplateData implements \JsonSerializable
{
    public function __construct(
        public readonly string $random,
        public readonly bool $show,
        public readonly bool $useDefault,
        public readonly string $url,
    ) {
    }

    /**
     * @return array{show: bool, useDefault: bool, url: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'show' => $this->show,
            'useDefault' => $this->useDefault,
            'url' => $this->url,
        ];
    }
}
