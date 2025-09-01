<?php declare(strict_types=1);

namespace Shopware\Administration\Login\TokenService;

use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;
use Lcobucci\JWT\Validator as ValidatorInterface;
use Shopware\Administration\Login\Config\LoginConfig;
use Shopware\Administration\Login\Config\LoginConfigService;
use Shopware\Administration\Login\LoginException;
use Shopware\Core\Framework\Log\Package;
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
            throw LoginException::configurationNotFound();
        }

        $token = $this->parser->parse($idToken);
        \assert($token instanceof UnencryptedToken);

        $kid = (string) $token->headers()->get('kid');
        $this->validateToken($kid, $loginConfig, $token);

        return ParsedIdToken::createFromDataSet($token->claims());
    }

    private function validateToken(string $kid, LoginConfig $loginConfig, UnencryptedToken $token, bool $bypassCacheLoading = false): void
    {
        $publicKey = $this->publicKeyLoader->loadPublicKey($kid, $bypassCacheLoading);

        $signatureConstraint = new SignedWith($this->algorithm, $publicKey);
        $issuedByConstraint = new IssuedBy($loginConfig->baseUrl);
        $validAtConstraint = new LooseValidAt($this->clock);

        if (!$this->validator->validate($token, $signatureConstraint, $issuedByConstraint, $validAtConstraint)) {
            if (!$bypassCacheLoading) {
                $this->validateToken($kid, $loginConfig, $token, true);

                return;
            }

            throw LoginException::invalidIdToken();
        }
    }
}
