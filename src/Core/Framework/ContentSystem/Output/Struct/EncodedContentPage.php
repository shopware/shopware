<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Carries an already-encoded content body through the framework's response encoding without being re-shaped by
 * it. The body reaches the wire because `jsonSerialize()` returns it verbatim and the framework encoder, finding
 * no object variable behind any of its keys, passes the nested arrays through and adds the top-level `apiAlias`.
 *
 * The alias travels with the body rather than being fixed here, because one carrier serves every format that
 * writes its own body and each of them reports its own alias; every producer passes its own alias explicitly.
 * `getApiAlias()` resolves through `??`, so an instance built without the constructor, which is how the
 * framework reads a struct's alias, yields the fallback constant instead of an uninitialized-property error.
 *
 * Two properties every one of those aliases has make the carrier safe, and both are constraints rather than
 * luck. None of them resolves to a registered entity definition, so the protection gate short-circuits instead
 * of judging content keys against some entity's fields.
 * {@see \Shopware\Tests\Integration\Core\Framework\ContentSystem\Output\ContentPageAliasRegistrationTest} pins
 * that constraint and fails if an entity is ever registered under one of those aliases. And no content body
 * carries a top-level key named `customFields`, `extensions` or `apiAlias`, the three keys the framework encoder
 * treats specially.
 *
 * A carrier exists only at the HTTP boundary. An in-process consumer reads the typed page off the route
 * response instead and never sees one.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class EncodedContentPage extends Struct
{
    private const FALLBACK_API_ALIAS = 'content_encoded_page';

    /**
     * @param array<string, mixed> $body
     */
    public function __construct(
        private readonly array $body,
        private readonly ?string $apiAlias = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->body;
    }

    public function getApiAlias(): string
    {
        return $this->apiAlias ?? self::FALLBACK_API_ALIAS;
    }
}
