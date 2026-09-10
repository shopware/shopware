<?php declare(strict_types=1);

namespace Shopware\Core\System\OAuthClient\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\Log\Package;

/**
 * A list of 1–20 OAuth redirect URLs, preserved exactly for redirect matching.
 * Allows HTTPS and loopback HTTP, without credentials, fragments or wildcards.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class RedirectUriListField extends ListField
{
    protected function getSerializerClass(): string
    {
        return RedirectUriListFieldSerializer::class;
    }
}
