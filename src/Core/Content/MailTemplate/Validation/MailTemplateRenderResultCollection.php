<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Validation;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<MailTemplateRenderResult, int|string>
 */
#[Package('after-sales')]
class MailTemplateRenderResultCollection extends Collection
{
    public function jsonSerialize(): array
    {
        return \array_map(fn (MailTemplateRenderResult $result) => $result->jsonSerialize(), $this->elements);
    }

}
