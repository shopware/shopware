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
use Shopware\Administration\Login\Config\LoginConfigService;
use Shopware\Administration\Login\Exception\LoginException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Clock\ClockInterface;

/**
 * @internal
 */
#[Package('after-sales')]
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

    public function parse(string $idToken): ParsedIdToken
    {
        $loginConfig = $this->loginConfigService->getConfig();

        /** for php-stan */
        /** @var UnencryptedToken $token */
        $token = $this->parser->parse($idToken);

        $kid = (string) $token->headers()->get('kid');
        $publicKey = $this->publicKeyLoader->loadPublicKey($kid);

        $signatureConstraint = new SignedWith($this->algorithm, $publicKey);
        $issuedByConstraint = new IssuedBy($loginConfig->baseUrl . '/');
        $validAtConstraint = new LooseValidAt($this->clock);

        if (!$this->validator->validate($token, $signatureConstraint, $issuedByConstraint, $validAtConstraint)) {
            throw LoginException::invalidIdToken();
        }

        return ParsedIdToken::createFromDataSet($token->claims());
    }
}
