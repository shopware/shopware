<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\File\Api;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class SalesChannelFileAdministrationTemplate
{
    public function __construct(
        public string $twigNamespace,
        public string $templateName,
        public string $templateContent,
        public string $role,
    ) {
    }
}
