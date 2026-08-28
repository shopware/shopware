<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Request;

use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
readonly class PreviewRequest
{
    /**
     * @param array<string,string> $entityMapping Associative array where the key is the variable name used in the template
     *                                            and the value is the corresponding entity ID.
     * @param array<string,mixed> $templateData Associative array where the key is the variable name used in the template
     *                                          and the value is the corresponding data to be used during rendering.
     */
    public function __construct(
        public MailTemplateEntity $mailTemplate,
        public ?SalesChannelEntity $salesChannel = null,
        public array $entityMapping = [],
        public array $templateData = [],
        public bool $includeHeaderFooter = false,
        public bool $strictRendering = false,
    ) {
    }
}
