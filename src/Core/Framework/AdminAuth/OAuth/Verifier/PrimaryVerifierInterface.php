<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\OAuth\Verifier;

use League\OAuth2\Server\Exception\OAuthServerException;
use Shopware\Core\Framework\Log\Package;

/**
 * A primary verifier validates the "first factor" of an admin login (e.g. username/password or an
 * external OAuth2/OIDC provider). It is the pluggable replacement for the hard-coded password
 * credential check of the core password grant.
 *
 * Implementations are registered with the DI tag `shopware.admin_auth.primary_verifier`.
 *
 * @internal intended as a public extension point once the ADMIN_AUTH feature is stable
 */
#[Package('framework')]
interface PrimaryVerifierInterface
{
    /**
     * Whether this verifier handles the given login method (the `method` request parameter,
     * e.g. "password").
     */
    public function supports(string $method): bool;

    /**
     * Verify the primary factor from the raw OAuth token request payload.
     *
     * @param array<string, mixed> $payload the parsed request body of POST /api/oauth/token
     *
     * @throws OAuthServerException when verification fails
     *
     * @return string the verified user's id as a hex string
     */
    public function verifyPrimary(array $payload): string;
}
