<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth;

use Lcobucci\JWT\Configuration;
use League\OAuth2\Server\CryptKey;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
final class FakeCryptKey extends CryptKey
{
    /**
     * @noinspection MagicMethodsValidityInspection
     *
     * @internal
     */
    public function __construct(public readonly Configuration $configuration)
    {
    }

    public function getKeyContents(): string
    {
        return '';
    }

    public function getKeyPath(): string
    {
        return '';
    }

    public function getPassPhrase(): string
    {
        return '';
    }
}
