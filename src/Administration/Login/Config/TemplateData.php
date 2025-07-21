<?php declare(strict_types=1);

namespace Shopware\Administration\Login\Config;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;

/**
 * @internal
 */
#[Package('framework')]
final class TemplateData implements \JsonSerializable
{
    use JsonSerializableTrait;

    public function __construct(
        public readonly bool $useDefault,
        public readonly ?string $url,
    ) {
    }
}
