<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Compatibility;

use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @see http://cve.mitre.org/cgi-bin/cvename.cgi?name=CVE-2021-41267
 *
 * @internal
 *
 * @deprecated tag:v6.4.7 - Increase minimum Symfony version
 */
class RequestTransformerFixer implements RequestTransformerInterface
{
    public const X_FORWARDED_PREFIX = 'x-forwarded-prefix';

    private RequestTransformerInterface $inner;

    public function __construct(RequestTransformerInterface $inner)
    {
        $this->inner = $inner;
    }

    public function transform(Request $request): Request
    {
        $req = $this->inner->transform($request);

        $trustedHeaderSet = Request::getTrustedHeaderSet();

        if ($req->headers->has(self::X_FORWARDED_PREFIX)) {
            if (($trustedHeaderSet & Request::HEADER_X_FORWARDED_PREFIX) === 0 || !$req->isFromTrustedProxy()) {
                $req->headers->remove(self::X_FORWARDED_PREFIX);

                return $req;
            }
        }

        return $req;
    }

    public function extractInheritableAttributes(Request $sourceRequest): array
    {
        return $this->inner->extractInheritableAttributes($sourceRequest);
    }
}
