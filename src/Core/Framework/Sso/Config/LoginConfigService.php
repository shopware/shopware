<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Sso\Config;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\SsoException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
final readonly class LoginConfigService
{
    /**
     * @param array{
     *     use_default: bool,
     *     client_id: non-empty-string,
     *     client_secret: non-empty-string,
     *     redirect_uri: non-empty-string,
     *     base_url: non-empty-string,
     *     authorize_path: non-empty-string,
     *     token_path: non-empty-string,
     *     jwks_path: non-empty-string,
     *     scope: non-empty-string,
     *     register_url: non-empty-string
     * } $rawConfig
     */
    public function __construct(
        private array $rawConfig,
        private RouterInterface $router,
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
            $this->rawConfig['authorize_path'],
            $this->rawConfig['token_path'],
            $this->rawConfig['jwks_path'],
            $this->rawConfig['scope'],
            $this->rawConfig['register_url'],
        );
    }

    public function createTemplateData(string $random): TemplateData
    {
        $loginConfig = $this->getConfig();

        return new TemplateData(
            $loginConfig->useDefault ?? true,
            $loginConfig ? $this->router->generate('oauth.sso.auth', ['rdm' => $random], UrlGeneratorInterface::ABSOLUTE_URL) : null,
        );
    }

    public function createRedirectUrl(string $random): string
    {
        $loginConfig = $this->getConfig();
        if (!$loginConfig instanceof LoginConfig) {
            throw SsoException::loginConfigurationNotFound();
        }

        $state = $this->router->generate('api.oauth.sso.code', ['rdm' => $random], UrlGeneratorInterface::ABSOLUTE_URL);

        return \sprintf(
            '%s%s?client_id=%s&redirect_uri=%s&response_type=code&scope=%s&state=%s',
            $loginConfig->baseUrl,
            $loginConfig->authorizePath,
            $loginConfig->clientId,
            \urlencode($loginConfig->redirectUri ?? ''),
            \urlencode($loginConfig->scope),
            \urlencode($state)
        );
    }

    private function validate(): void
    {
        $violations = Validation::createValidator()->validate($this->rawConfig, $this->createConstraint());
        if ($violations->count() === 0) {
            return;
        }

        $missingConfiguredFields = [];
        foreach ($violations as $violation) {
            $missingConfiguredFields[] = $violation->getPropertyPath() . ' ' . $violation->getMessageTemplate();
        }

        throw SsoException::configurationMisconfigured($missingConfiguredFields);
    }

    private function createConstraint(): Collection
    {
        $isNullMessage = 'is null';
        $notBlankMessage = 'is blank';
        $invalidStringMessage = 'is invalid string';
        $invalidUrlMessage = 'is invalid URL';
        $invalidPath = 'is invalid path. Requires to start with "/"';

        return new Collection(
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
                    new Url(message: $invalidUrlMessage, requireTld: true),
                ],
                'base_url' => [
                    new NotNull(null, $isNullMessage),
                    new NotBlank(null, $notBlankMessage),
                    new Type('string', $invalidStringMessage),
                    new Url(message: $invalidUrlMessage, requireTld: true),
                    new Regex('/\w+(?!\/)$/', 'should not end with "/"'),
                ],
                'authorize_path' => [
                    new NotNull(null, $isNullMessage),
                    new NotBlank(null, $notBlankMessage),
                    new Type('string', $invalidStringMessage),
                    new Regex('/^[\/].+$/', $invalidPath), // path should start with "/"
                ],
                'token_path' => [
                    new NotNull(null, $isNullMessage),
                    new NotBlank(null, $notBlankMessage),
                    new Type('string', $invalidStringMessage),
                    new Regex('/^[\/].+$/', $invalidPath), // path should start with "/"
                ],
                'jwks_path' => [
                    new NotNull(null, $isNullMessage),
                    new NotBlank(null, $notBlankMessage),
                    new Type('string', $invalidStringMessage),
                    new Regex('/^[\/].+$/', $invalidPath), // path should start with "/"
                ],
                'scope' => [
                    new NotNull(null, $isNullMessage),
                    new NotBlank(null, $notBlankMessage),
                    new Type('string', $invalidStringMessage),
                ],
                'register_url' => [
                    new NotNull(null, $isNullMessage),
                    new NotBlank(null, $notBlankMessage),
                    new Type('string', $invalidStringMessage),
                    new Url(message: $invalidUrlMessage, requireTld: true),
                ],
            ],
            allowExtraFields: true,
            allowMissingFields: false,
            missingFieldsMessage: 'is missing'
        );
    }
}
