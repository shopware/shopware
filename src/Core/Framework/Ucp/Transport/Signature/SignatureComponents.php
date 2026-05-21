<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\Signature;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Value-object holding the components covered by an RFC 9421 signature and
 * the parameters (`keyid`, `created`, `expires`, `nonce`, `tag`, `alg`) that
 * appear in the `Signature-Input` header.
 *
 * Component identifiers follow RFC 9421 §2: derived components are prefixed
 * with `@` (e.g. `@method`, `@target-uri`), regular HTTP headers use their
 * lower-case name.
 *
 * @internal
 */
#[Package('framework')]
final class SignatureComponents
{
    /**
     * @param list<string> $components covered component identifiers, in order
     * @param array<string, string> $parameters parameter map (`keyid`, `created`, …)
     */
    public function __construct(
        public readonly array $components,
        public readonly array $parameters,
    ) {
    }

    /**
     * Default component set for inbound UCP requests. UCP RECOMMENDS covering
     * method, target URI, content-digest and any UCP-specific headers like
     * `UCP-Agent`.
     *
     * @return list<string>
     */
    public static function forInboundRequest(): array
    {
        return ['@method', '@target-uri', 'content-digest', 'ucp-agent'];
    }

    /**
     * @return list<string>
     */
    public static function forOutboundWebhook(): array
    {
        return ['@method', '@target-uri', 'content-digest', 'content-type'];
    }

    public function getParameter(string $name): ?string
    {
        return $this->parameters[$name] ?? null;
    }

    public function getKeyId(): ?string
    {
        return $this->getParameter('keyid');
    }

    public function getCreated(): ?int
    {
        $v = $this->getParameter('created');

        return $v === null ? null : (int) $v;
    }

    public function getExpires(): ?int
    {
        $v = $this->getParameter('expires');

        return $v === null ? null : (int) $v;
    }

    public function getTag(): ?string
    {
        return $this->getParameter('tag');
    }

    public function getNonce(): ?string
    {
        return $this->getParameter('nonce');
    }
}
