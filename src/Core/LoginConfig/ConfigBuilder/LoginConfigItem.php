<?php declare(strict_types=1);

namespace Shopware\Core\LoginConfig\ConfigBuilder;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\LoginConfig\LoginConfigException;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('core')]
class LoginConfigItem
{
    /**
     * @param array<string, mixed> $additionalData
     */
    public function __construct(
        public readonly string $configKey,
        public readonly string $snippetKey,
        public readonly string $icon,
        public readonly string $class,
        public readonly string $clientId,
        public readonly string $clientSecret,
        public readonly string $redirectUri,
        public readonly string $baseUrl,
        public readonly ?array $additionalData = [],
    ) {
    }

    /**
     * @param array{snippet_key: string, icon: string, class: string, client_id: string, client_secret: string, redirect_uri: string, base_url: string, additional_data: ?array<string, mixed>} $array
     */
    public static function fromArray(string $key, array $array): LoginConfigItem
    {
        self::validate($array);

        return new self(
            $key,
            $array['snippet_key'],
            $array['icon'],
            $array['class'],
            $array['client_id'],
            $array['client_secret'],
            $array['redirect_uri'],
            $array['base_url'],
            $array['additional_data'] ?? [],
        );
    }

    /**
     * @param array{snippet_key: string, icon: string, class: string, client_id: string, client_secret: string, redirect_uri: string, base_url: string, additional_data: ?array<string, mixed>} $array
     */
    private static function validate(array $array): void
    {
        $validator = Validation::createValidator();
        $violations = $validator->validate($array, self::createConstraint());
        if ($violations->count() === 0) {
            return;
        }

        $missingConfigFields = [];
        for ($i = 0; $i < $violations->count(); ++$i) {
            $missingConfigFields[] = $violations->get($i)->getPropertyPath();
        }

        throw LoginConfigException::configurationIncomplete($missingConfigFields);
    }

    private static function createConstraint(): Collection
    {
        $constraints = new Collection([
            'snippet_key' => new NotBlank(),
            'icon' => new NotBlank(),
            'class' => new NotBlank(),
            'client_id' => new NotBlank(),
            'client_secret' => new NotBlank(),
            'redirect_uri' => new NotBlank(),
            'base_url' => new NotBlank(),
        ]);

        $constraints->allowExtraFields = true;

        return $constraints;
    }
}
