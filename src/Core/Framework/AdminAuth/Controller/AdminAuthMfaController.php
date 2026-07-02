<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\Controller;

use Doctrine\DBAL\Connection;
use OTPHP\TOTP;
use Shopware\Core\Framework\AdminAuth\AdminAuthException;
use Shopware\Core\Framework\AdminAuth\Entity\UserMethod\AdminAuthUserMethodCollection;
use Shopware\Core\Framework\AdminAuth\MethodSettingsService;
use Shopware\Core\Framework\AdminAuth\OAuth\Verifier\WebAuthnVerifier;
use Shopware\Core\Framework\AdminAuth\SecretEncryptor;
use Shopware\Core\Framework\AdminAuth\WebAuthn\WebAuthnChallengeStore;
use Shopware\Core\Framework\AdminAuth\WebAuthn\WebAuthnService;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Controller\UserController;
use Shopware\Core\Framework\Api\OAuth\Scope\UserVerifiedScope;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Webauthn\PublicKeyCredentialDescriptor;

/**
 * Self-service endpoints for a logged-in admin to manage their own second factors (TOTP, passkeys,
 * recovery codes).
 *
 * Every route acts on the *current* user from the admin context — no route accepts a foreign user
 * id. Enrollment-mutating routes additionally require the `user-verified` OAuth scope (a fresh
 * password re-verification via the admin's `verifyUserToken`), the same guard core applies to
 * sensitive profile changes in {@see UserController}.
 *
 * @experimental stableVersion:v6.9.0 feature:ADMIN_AUTH
 *
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('framework')]
class AdminAuthMfaController extends AbstractController
{
    private const RECOVERY_CODE_COUNT = 10;

    private const TYPE_TOTP = 'totp';
    private const TYPE_RECOVERY_CODES = 'recovery_codes';

    /**
     * Default label in the otpauth provisioning URI (shown by the authenticator app).
     */
    private const DEFAULT_TOTP_LABEL = 'Shopware Admin';

    /**
     * @param EntityRepository<AdminAuthUserMethodCollection> $userMethodRepository
     */
    public function __construct(
        private readonly EntityRepository $userMethodRepository,
        private readonly SecretEncryptor $secretEncryptor,
        private readonly WebAuthnService $webAuthnService,
        private readonly WebAuthnChallengeStore $webAuthnChallengeStore,
        private readonly MethodSettingsService $methodSettings,
        private readonly Connection $connection,
    ) {
    }

    /**
     * Lists the current user's enrolled methods. Secrets and credentials are never exposed.
     */
    #[Route(
        path: '/api/_action/admin-auth/mfa/methods',
        name: 'api.admin_auth.mfa.methods.list',
        methods: [Request::METHOD_GET]
    )]
    public function listMethods(Context $context, Request $request): JsonResponse
    {
        $this->ensureFeatureActive();
        $userId = $this->requireUser($context, $request, requireVerified: false);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('userId', $userId));
        $methods = $this->userMethodRepository->search($criteria, $context)->getEntities();

        $result = [];
        foreach ($methods as $method) {
            $entry = [
                'id' => $method->getId(),
                'type' => $method->getType(),
                'active' => $method->isActive(),
                'label' => $method->getLabel(),
                'lastUsedAt' => $method->getLastUsedAt()?->format(\DateTimeInterface::ATOM),
            ];

            if ($method->getType() === self::TYPE_RECOVERY_CODES) {
                $entry['remaining'] = $this->remainingRecoveryCodes($method->getCredential());
            }

            $result[] = $entry;
        }

        return new JsonResponse(['methods' => $result]);
    }

    /**
     * Begins a TOTP enrollment: generates a fresh secret, stores it server-side as an *inactive*
     * method row and returns the secret + otpauth URI exactly once for the QR code.
     */
    #[Route(
        path: '/api/_action/admin-auth/mfa/totp/register/options',
        name: 'api.admin_auth.mfa.totp.register.options',
        methods: [Request::METHOD_POST]
    )]
    public function totpRegisterOptions(Context $context, Request $request): JsonResponse
    {
        $this->ensureFeatureActive();
        $userId = $this->requireUser($context, $request, requireVerified: true);
        $this->ensureMethodEnabled(self::TYPE_TOTP);

        $label = trim((string) $request->request->get('label', ''));
        $totp = TOTP::generate()->withLabel($label !== '' ? $label : self::DEFAULT_TOTP_LABEL);
        $secret = $totp->getSecret();

        $id = Uuid::randomHex();
        $this->userMethodRepository->create([[
            'id' => $id,
            'userId' => $userId,
            'type' => self::TYPE_TOTP,
            'active' => false,
            'label' => $label !== '' ? $label : 'Authenticator app',
            'secret' => $this->secretEncryptor->encrypt($secret),
        ]], $context);

        return new JsonResponse([
            'id' => $id,
            'secret' => $secret,
            'otpauthUri' => $totp->getProvisioningUri(),
        ]);
    }

    /**
     * Completes a TOTP enrollment: activates the pending method row once the user proves possession
     * of the seed with a valid code.
     */
    #[Route(
        path: '/api/_action/admin-auth/mfa/totp/register/verify',
        name: 'api.admin_auth.mfa.totp.register.verify',
        methods: [Request::METHOD_POST]
    )]
    public function totpRegisterVerify(Context $context, Request $request): JsonResponse
    {
        $this->ensureFeatureActive();
        $userId = $this->requireUser($context, $request, requireVerified: true);
        $this->ensureMethodEnabled(self::TYPE_TOTP);

        $methodId = (string) $request->request->get('id');
        $code = (string) $request->request->get('code');

        if (!Uuid::isValid($methodId) || $code === '' || preg_match('/^\d{6}$/', $code) !== 1) {
            throw AdminAuthException::invalidMfaCode();
        }

        $criteria = new Criteria([$methodId]);
        $criteria->addFilter(new EqualsFilter('userId', $userId));
        $criteria->addFilter(new EqualsFilter('type', self::TYPE_TOTP));
        $method = $this->userMethodRepository->search($criteria, $context)->getEntities()->first();

        if ($method === null || $method->getSecret() === null) {
            throw AdminAuthException::mfaEnrollmentNotFound();
        }

        $secret = $this->secretEncryptor->decrypt($method->getSecret());
        if ($secret === '') {
            throw AdminAuthException::mfaEnrollmentNotFound();
        }

        if (!TOTP::createFromSecret($secret)->verify($code, null, 1)) {
            throw AdminAuthException::invalidMfaCode();
        }

        $this->userMethodRepository->update([[
            'id' => $methodId,
            'active' => true,
        ]], $context);

        return new JsonResponse(['success' => true]);
    }

    /**
     * Begins a passkey enrollment: returns WebAuthn creation options (existing credentials excluded)
     * plus the signed challenge token the verify call must echo back.
     */
    #[Route(
        path: '/api/_action/admin-auth/mfa/webauthn/register/options',
        name: 'api.admin_auth.mfa.webauthn.register.options',
        methods: [Request::METHOD_POST]
    )]
    public function webauthnRegisterOptions(Context $context, Request $request): JsonResponse
    {
        $this->ensureFeatureActive();
        $userId = $this->requireUser($context, $request, requireVerified: true);
        $this->ensureMethodEnabled(WebAuthnVerifier::METHOD);

        [$username, $displayName] = $this->userDisplay($userId);

        $options = $this->webAuthnService->createRegistrationOptions(
            $userId,
            $username,
            $displayName,
            $this->existingDescriptors($userId)
        );

        $optionsJson = $this->webAuthnService->serializeOptions($options);

        return new JsonResponse([
            'options' => json_decode($optionsJson, true),
            'challengeToken' => $this->webAuthnChallengeStore->issue(
                $optionsJson,
                WebAuthnChallengeStore::PURPOSE_REGISTER,
                $userId
            ),
        ]);
    }

    /**
     * Completes a passkey enrollment: verifies the authenticator's attestation response against the
     * challenge and stores the credential record as an active method row.
     */
    #[Route(
        path: '/api/_action/admin-auth/mfa/webauthn/register/verify',
        name: 'api.admin_auth.mfa.webauthn.register.verify',
        methods: [Request::METHOD_POST]
    )]
    public function webauthnRegisterVerify(Context $context, Request $request): JsonResponse
    {
        $this->ensureFeatureActive();
        $userId = $this->requireUser($context, $request, requireVerified: true);
        $this->ensureMethodEnabled(WebAuthnVerifier::METHOD);

        $credential = (string) $request->request->get('credential');
        $label = (string) $request->request->get('label', 'Passkey');
        $challengeToken = $request->request->get('challengeToken');
        $optionsJson = $this->webAuthnChallengeStore->consume(
            \is_string($challengeToken) ? $challengeToken : null,
            WebAuthnChallengeStore::PURPOSE_REGISTER,
            $userId
        );

        if ($credential === '' || $optionsJson === null) {
            throw AdminAuthException::webAuthnRegistrationFailed('No active registration challenge.');
        }

        try {
            $options = $this->webAuthnService->deserializeCreationOptions($optionsJson);
            $record = $this->webAuthnService->verifyRegistration($credential, $options, $request->getHost());
        } catch (\Throwable $exception) {
            throw AdminAuthException::webAuthnRegistrationFailed($exception->getMessage(), $exception);
        }

        $id = Uuid::randomHex();
        $this->userMethodRepository->create([[
            'id' => $id,
            'userId' => $userId,
            'type' => WebAuthnVerifier::METHOD,
            'active' => true,
            'label' => $label,
            'credential' => json_decode($this->webAuthnService->serializeRecord($record), true),
        ]], $context);

        return new JsonResponse(['id' => $id, 'success' => true]);
    }

    /**
     * (Re-)generates the recovery-code set. The plaintext codes are returned exactly once and only
     * their hashes are stored; regenerating invalidates all previous codes.
     */
    #[Route(
        path: '/api/_action/admin-auth/mfa/recovery-codes',
        name: 'api.admin_auth.mfa.recovery_codes.generate',
        methods: [Request::METHOD_POST]
    )]
    public function generateRecoveryCodes(Context $context, Request $request): JsonResponse
    {
        $this->ensureFeatureActive();
        $userId = $this->requireUser($context, $request, requireVerified: true);
        $this->ensureMethodEnabled(self::TYPE_RECOVERY_CODES);

        $plainCodes = [];
        $stored = [];
        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; ++$i) {
            $code = $this->randomRecoveryCode();
            $plainCodes[] = $code;
            // Hash the canonical (dash-stripped) form so verification is grouping-agnostic.
            $canonical = str_replace('-', '', $code);
            $stored[] = ['hash' => password_hash($canonical, \PASSWORD_DEFAULT), 'usedAt' => null];
        }

        // Replace any existing recovery-code set: regenerating invalidates the old codes.
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('userId', $userId));
        $criteria->addFilter(new EqualsFilter('type', self::TYPE_RECOVERY_CODES));
        $existingIds = $this->userMethodRepository->searchIds($criteria, $context)->getIds();
        if ($existingIds !== []) {
            $this->userMethodRepository->delete(array_map(static fn ($id) => ['id' => $id], $existingIds), $context);
        }

        $this->userMethodRepository->create([[
            'id' => Uuid::randomHex(),
            'userId' => $userId,
            'type' => self::TYPE_RECOVERY_CODES,
            'active' => true,
            'label' => 'Recovery codes',
            'credential' => ['codes' => $stored],
        ]], $context);

        return new JsonResponse(['codes' => $plainCodes]);
    }

    /**
     * Deletes one of the current user's own methods. Foreign method ids are treated as non-existent.
     */
    #[Route(
        path: '/api/_action/admin-auth/mfa/methods/{id}',
        name: 'api.admin_auth.mfa.methods.delete',
        methods: [Request::METHOD_DELETE]
    )]
    public function deleteMethod(string $id, Context $context, Request $request): Response
    {
        $this->ensureFeatureActive();
        $userId = $this->requireUser($context, $request, requireVerified: true);

        if (!Uuid::isValid($id)) {
            return new Response(status: Response::HTTP_NO_CONTENT);
        }

        $criteria = new Criteria([$id]);
        $criteria->addFilter(new EqualsFilter('userId', $userId));
        $method = $this->userMethodRepository->search($criteria, $context)->getEntities()->first();

        if ($method !== null) {
            $this->userMethodRepository->delete([['id' => $id]], $context);
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function ensureFeatureActive(): void
    {
        if (!Feature::isActive('ADMIN_AUTH')) {
            throw AdminAuthException::featureNotActive();
        }
    }

    /**
     * The YAML configuration is a hard gate: enrollment into a disabled method must be impossible,
     * not just hidden. Runtime-disabled methods (Methods admin tab) are rejected the same way.
     */
    private function ensureMethodEnabled(string $type): void
    {
        if (!$this->methodSettings->isEnabled($type)) {
            throw AdminAuthException::methodDisabled($type);
        }
    }

    /**
     * Format: two 4-character groups, e.g. "A1B2-C3D4" (ambiguous characters omitted).
     */
    private function randomRecoveryCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $chars = '';
        for ($i = 0; $i < 8; ++$i) {
            $chars .= $alphabet[random_int(0, \strlen($alphabet) - 1)];
        }

        return substr($chars, 0, 4) . '-' . substr($chars, 4, 4);
    }

    /**
     * @param array<string, mixed>|null $credential
     */
    private function remainingRecoveryCodes(?array $credential): int
    {
        $codes = \is_array($credential['codes'] ?? null) ? $credential['codes'] : [];

        return \count(array_filter($codes, static fn ($c) => \is_array($c) && ($c['usedAt'] ?? null) === null));
    }

    /**
     * @return array{0: string, 1: string} [username, displayName]
     */
    private function userDisplay(string $userId): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT username, CONCAT_WS(" ", first_name, last_name) AS name FROM `user` WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($userId)]
        );

        $username = \is_array($row) && \is_string($row['username'] ?? null) ? $row['username'] : $userId;
        $name = \is_array($row) && \is_string($row['name'] ?? null) && trim($row['name']) !== '' ? $row['name'] : $username;

        return [$username, $name];
    }

    /**
     * @return list<PublicKeyCredentialDescriptor>
     */
    private function existingDescriptors(string $userId): array
    {
        $rows = $this->connection->fetchFirstColumn(
            'SELECT credential FROM admin_auth_user_method WHERE user_id = :id AND type = :type AND active = 1',
            ['id' => Uuid::fromHexToBytes($userId), 'type' => WebAuthnVerifier::METHOD]
        );

        $descriptors = [];
        foreach ($rows as $json) {
            if (!\is_string($json)) {
                continue;
            }

            try {
                $record = $this->webAuthnService->deserializeRecord($json);
                $descriptors[] = $this->webAuthnService->descriptorFromRecord($record);
            } catch (\Throwable) {
                // skip unreadable credentials
            }
        }

        return $descriptors;
    }

    /**
     * @return string the current admin user's id (hex)
     */
    private function requireUser(Context $context, Request $request, bool $requireVerified): string
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource || $source->getUserId() === null) {
            throw AdminAuthException::missingUserContext();
        }

        if ($requireVerified) {
            $scopes = $request->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_SCOPES, []);
            $scopes = \is_array($scopes) ? $scopes : [];
            if (!\in_array(UserVerifiedScope::IDENTIFIER, $scopes, true)) {
                throw AdminAuthException::userNotVerified();
            }
        }

        return $source->getUserId();
    }
}
