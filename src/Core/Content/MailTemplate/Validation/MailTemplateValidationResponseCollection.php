<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Validation;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<MailTemplateValidationResponse>
 */
#[Package('after-sales')]
class MailTemplateValidationResponseCollection extends Collection
{
    public function jsonSerialize(): array
    {
        return array_map(fn(MailTemplateValidationResponse $el) => $el->jsonSerialize(), $this->elements);
    }
}
