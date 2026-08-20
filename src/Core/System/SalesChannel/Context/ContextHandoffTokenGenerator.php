<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Token\RegisteredClaims;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Shopware\Core\Framework\JWT\SalesChannel\JWTGenerator;
use Shopware\Core\Framework\JWT\Struct\JWTStruct;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\Struct\ContextHandoffToken;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 *
 * @extends JWTGenerator<ContextHandoffToken>
 */
#[Package('framework')]
class ContextHandoffTokenGenerator extends JWTGenerator
{
    public const AUDIENCE = 'context-handoff';

    public const TOKEN_LIFETIME_IN_SECONDS = 60;

    /**
     * @internal
     */
    public function __construct(
        private readonly Configuration $configuration,
        private readonly DataValidator $validator,
    ) {
        parent::__construct($this->configuration, $this->validator);
    }

    protected function getJWTStructClass(): string
    {
        return ContextHandoffToken::class;
    }

    protected function getStructConstraints(): DataValidationDefinition
    {
        $definition = parent::getStructConstraints();
        $definition->set(RegisteredClaims::AUDIENCE, new NotBlank(), new NotNull(), new Type('array'));
        $definition->add(RegisteredClaims::ID, new NotBlank(), new NotNull());
        $definition->add('salesChannelId', new NotBlank(), new NotNull(), new Type('string'));

        return $definition;
    }

    protected function getTokenValidationConstraints(): array
    {
        return [...parent::getTokenValidationConstraints(), new PermittedFor(self::AUDIENCE)];
    }

    protected function getTokenLifetime(JWTStruct $jwt): int
    {
        return self::TOKEN_LIFETIME_IN_SECONDS;
    }
}
