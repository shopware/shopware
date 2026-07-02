<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\OAuth\Verifier;

use League\OAuth2\Server\Exception\OAuthServerException;
use Shopware\Core\Framework\AdminAuth\Mfa\MfaChallenge;
use Shopware\Core\Framework\Log\Package;

/**
 * A second-factor verifier validates the second leg of an MFA login (e.g. a TOTP code or a WebAuthn
 * assertion) against the user's enrolled factor.
 *
 * Implementations are registered with the DI tag `shopware.admin_auth.second_factor_verifier`.
 *
 * @internal intended as a public extension point once the ADMIN_AUTH feature is stable
 */
#[Package('framework')]
interface SecondFactorVerifierInterface
{
    /**
     * Whether this verifier handles the given second-factor method (the `method` request parameter,
     * e.g. "totp").
     */
    public function supports(string $method): bool;

    /**
     * Verify the second factor for the challenged user.
     *
     * @param array<string, mixed> $payload the parsed request body of the second-factor token request
     *
     * @throws OAuthServerException when verification fails
     */
    public function verifySecondFactor(string $userId, array $payload, MfaChallenge $challenge): void;
}
