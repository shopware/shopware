<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\File\Api;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class SalesChannelFileAdministrationListItem
{
    public function __construct(
        public string $fileFamily,
        public string $fileName,
        public string $contentType,
        public ?SalesChannelFileAdministrationConfiguration $configuration,
    ) {
    }
}
