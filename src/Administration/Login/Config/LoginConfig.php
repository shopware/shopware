<?php declare(strict_types=1);

namespace Shopware\Administration\Login\Config;

use Shopware\Administration\Login\Exception\LoginException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\String\ByteString;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('after-sales')]
final class LoginConfig
{
    public const RANDOM_LENGTH = 64;

    private bool $isEmpty;

    private ?bool $useDefault = null;

    private ?string $clientId = null;

    private ?string $clientSecret = null;

    private ?string $redirectUri = null;

    private ?string $baseUrl = null;

    /**
     * @param array<string, mixed> $loginConfig
     */
    public function __construct(
        private readonly array $loginConfig,
        private readonly string $appUrl,
        protected readonly string $adminPath,
    ) {
        $this->isEmpty = \count($this->loginConfig) === 0;
        if ($this->isEmpty) {
            return;
        }

        $this->validate($loginConfig);
        $this->useDefault = $loginConfig['use_default'];
        $this->clientId = $loginConfig['client_id'];
        $this->clientSecret = $loginConfig['client_secret'];
        $this->redirectUri = $loginConfig['redirect_uri'];
        $this->baseUrl = $loginConfig['base_url'];
    }

    public function createTemplateData(): TemplateData
    {
        $random = ByteString::fromRandom(self::RANDOM_LENGTH)->toString();

        return new TemplateData(
            $random,
            !$this->isEmpty,
            $this->useDefault ?? true,
            \sprintf('%s/%s/sso/auth?rdm=%s', $this->appUrl, $this->adminPath, $random),
        );
    }

    public function createRedirectUrl(string $random): string
    {
        $state = \sprintf('%s/api/oauth/sso/code?rdm=%s', $this->appUrl, $random);

        return \sprintf(
            '%s/oauth/authorize?client_id=%s&redirect_uri=%s&response_type=code&scope=openid&state=%s',
            $this->baseUrl,
            $this->clientId,
            \urlencode($this->redirectUri ?? ''),
            \urlencode($state)
        );
    }

    public function isEmpty(): bool
    {
        return $this->isEmpty;
    }

    public function getUseDefault(): ?bool
    {
        if ($this->isEmpty) {
            return true;
        }

        return $this->useDefault;
    }

    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    public function getClientSecret(): ?string
    {
        return $this->clientSecret;
    }

    public function getRedirectUri(): ?string
    {
        return $this->redirectUri;
    }

    public function getBaseUrl(): ?string
    {
        return $this->baseUrl;
    }

    /**
     * @param array<string, mixed> $loginConfig
     */
    private function validate(array $loginConfig): void
    {
        $validator = Validation::createValidator();
        $violations = $validator->validate($loginConfig, self::createConstraint());
        if ($violations->count() === 0) {
            return;
        }

        $missingConfiguredFields = [];
        foreach ($violations as $violation) {
            $missingConfiguredFields[] = $violation->getPropertyPath() . ' ' . $violation->getMessageTemplate();
        }

        throw LoginException::configurationMisconfigured($missingConfiguredFields);
    }

    private static function createConstraint(): Collection
    {
        $urlMessage = 'is invalid URL';
        $notBlankMessage = 'is blank';
        $constraints = new Collection(
            [
                'use_default' => new Type('boolean', 'is not a boolean'),
                'client_id' => new NotBlank(null, $notBlankMessage),
                'client_secret' => new NotBlank(null, $notBlankMessage),
                'redirect_uri' => [
                    new NotBlank(null, $notBlankMessage),
                    new Url(null, $urlMessage),
                ],
                'base_url' => [
                    new NotBlank(null, $notBlankMessage),
                    new Url(null, $urlMessage),
                ],
            ],
            null,
            null,
            false,
            false,
            null,
            'is missing'
        );

        $constraints->allowExtraFields = true;

        return $constraints;
    }
}
