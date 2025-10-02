<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Sso\TokenService;

use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;
use Lcobucci\JWT\Validator as ValidatorInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\Config\LoginConfig;
use Shopware\Core\Framework\Sso\Config\LoginConfigService;
use Shopware\Core\Framework\Sso\SsoException;
use Symfony\Component\Clock\ClockInterface;

/**
 * @internal
 */
#[Package('framework')]
final class IdTokenParser
{
    private Parser $parser;

    private ValidatorInterface $validator;

    private Sha256 $algorithm;

    public function __construct(
        private readonly PublicKeyLoader $publicKeyLoader,
        private readonly LoginConfigService $loginConfigService,
        private readonly ClockInterface $clock,
    ) {
        $this->parser = new Parser(new JoseEncoder());
        $this->validator = new Validator();
        $this->algorithm = new Sha256();
    }

    /**
     * @param non-empty-string $idToken
     */
    public function parse(string $idToken): ParsedIdToken
    {
        $loginConfig = $this->loginConfigService->getConfig();
        if (!$loginConfig instanceof LoginConfig) {
            throw SsoException::loginConfigurationNotFound();
        }

        $token = $this->parser->parse($idToken);
        \assert($token instanceof UnencryptedToken);

        $kid = (string) $token->headers()->get('kid');
        $this->validateToken($kid, $loginConfig, $token);

        return ParsedIdToken::createFromDataSet($token->claims());
    }

    private function validateToken(string $kid, LoginConfig $loginConfig, UnencryptedToken $token, bool $bypassCacheLoading = false): void
    {
        if (!$this->validateIssuedBy($loginConfig, $token)) {
            throw SsoException::invalidIdToken();
        }

        $publicKey = $this->publicKeyLoader->loadPublicKey($kid, $bypassCacheLoading);

        $signatureConstraint = new SignedWith($this->algorithm, $publicKey);
        $validAtConstraint = new LooseValidAt($this->clock);

        if (!$this->validator->validate($token, $signatureConstraint, $validAtConstraint)) {
            if (!$bypassCacheLoading) {
                $this->validateToken($kid, $loginConfig, $token, true);

                return;
            }

            throw SsoException::invalidIdToken();
        }
    }

    private function validateIssuedBy(LoginConfig $loginConfig, UnencryptedToken $token): bool
    {
        $issuedByConstraint = new IssuedBy($loginConfig->baseUrl);
        if ($this->validator->validate($token, $issuedByConstraint)) {
            return true;
        }

        $issuedByConstraint = new IssuedBy($loginConfig->baseUrl . '/');

        return $this->validator->validate($token, $issuedByConstraint);
    }
}
