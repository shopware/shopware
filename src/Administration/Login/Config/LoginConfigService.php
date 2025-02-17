<?php declare(strict_types=1);

namespace Shopware\Administration\Login\Config;

use Shopware\Administration\Login\Exception\LoginException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('after-sales')]
final class LoginConfigService
{
    /**
     * @param array{use_default: bool, client_id: non-empty-string, client_secret: non-empty-string, redirect_uri: non-empty-string, base_url: non-empty-string, authorize_endpoint: non-empty-string, token_endpoint: non-empty-string} $rawConfig
     */
    public function __construct(
        private readonly array $rawConfig,
        private readonly string $appUrl,
        private readonly string $adminPath,
    ) {
    }

    public function getConfig(): ?LoginConfig
    {
        if (\count($this->rawConfig) === 0) {
            return null;
        }

        $this->validate();

        return new LoginConfig(
            $this->rawConfig['use_default'],
            $this->rawConfig['client_id'],
            $this->rawConfig['client_secret'],
            $this->rawConfig['redirect_uri'],
            $this->rawConfig['base_url'],
            $this->rawConfig['authorize_endpoint'],
            $this->rawConfig['token_endpoint'],
        );
    }

    public function createTemplateData(string $random, ?LoginConfig $loginConfig): TemplateData
    {
        return new TemplateData(
            $loginConfig->useDefault ?? true,
            $loginConfig === null ? null : \sprintf('%s/%s/sso/auth?rdm=%s', $this->appUrl, \ltrim($this->adminPath, '/'), $random),
        );
    }

    public function createRedirectUrl(string $random, LoginConfig $loginConfig): string
    {
        $state = \sprintf('%s/api/oauth/sso/code?rdm=%s', $this->appUrl, $random);

        return \sprintf(
            '%s%s?client_id=%s&redirect_uri=%s&response_type=code&scope=openid&state=%s',
            $loginConfig->baseUrl,
            $loginConfig->authorizeEndpoint,
            $loginConfig->clientId,
            \urlencode($loginConfig->redirectUri ?? ''),
            \urlencode($state)
        );
    }

    private function validate(): void
    {
        $validator = Validation::createValidator();
        $violations = $validator->validate($this->rawConfig, self::createConstraint());
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
        $isNullMessage = 'is null';
        $notBlankMessage = 'is blank';
        $invalidStringMessage = 'is invalid string';
        $invalidUrlMessage = 'is invalid URL';

        $constraints = new Collection(
            [
                'use_default' => [
                    new NotNull(null, $isNullMessage),
                    new Type('bool', 'is not a boolean'),
                ],
                'client_id' => [
                    new NotNull(null, $isNullMessage),
                    new NotBlank(null, $notBlankMessage),
                    new Type('string', $invalidStringMessage),
                ],
                'client_secret' => [
                    new NotNull(null, $isNullMessage),
                    new NotBlank(null, $notBlankMessage),
                    new Type('string', $invalidStringMessage),
                ],
                'redirect_uri' => [
                    new NotNull(null, $isNullMessage),
                    new NotBlank(null, $notBlankMessage),
                    new Type('string', $invalidStringMessage),
                    new Url(null, $invalidUrlMessage),
                ],
                'base_url' => [
                    new NotNull(null, $isNullMessage),
                    new NotBlank(null, $notBlankMessage),
                    new Type('string', $invalidStringMessage),
                    new Url(null, $invalidUrlMessage),
                ],
                'authorize_endpoint' => [
                    new NotNull(null, $isNullMessage),
                    new NotBlank(null, $notBlankMessage),
                    new Type('string', $invalidStringMessage),
                ],
                'token_endpoint' => [
                    new NotNull(null, $isNullMessage),
                    new NotBlank(null, $notBlankMessage),
                    new Type('string', $invalidStringMessage),
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
