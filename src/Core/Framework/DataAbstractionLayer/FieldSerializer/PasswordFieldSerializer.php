<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\PasswordHasher\PasswordHasherRegistry;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
class PasswordFieldSerializer extends AbstractFieldSerializer
{
    public const CONFIG_MIN_LENGTH_FOR = [
        PasswordField::FOR_CUSTOMER => 'core.loginRegistration.passwordMinLength',
        PasswordField::FOR_ADMIN => 'core.userPermission.passwordMinLength',
    ];

    /**
     * @internal
     */
    public function __construct(
        ValidatorInterface $validator,
        DefinitionInstanceRegistry $definitionRegistry,
        private readonly SystemConfigService $configService,
        private readonly PasswordHasherRegistry $passwordHasherRegistry,
    ) {
        parent::__construct($validator, $definitionRegistry);
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof PasswordField) {
            throw DataAbstractionLayerException::invalidSerializerField(PasswordField::class, $field);
        }

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        $value = $data->getValue();
        if ($value) {
            $value = $this->hash((string) $value, $field);
        }

        yield $field->getStorageName() => $value;
    }

    public function decode(Field $field, mixed $value): ?string
    {
        return $value;
    }

    /**
     * @param PasswordField $field
     */
    protected function getConstraints(Field $field): array
    {
        $constraints = [
            new NotBlank(),
            new Type('string'),
        ];

        if ($field->getFor() === null || !\array_key_exists($field->getFor(), self::CONFIG_MIN_LENGTH_FOR)) {
            return $constraints;
        }

        $configKey = self::CONFIG_MIN_LENGTH_FOR[$field->getFor()];

        $minPasswordLength = $this->configService->getInt($configKey);

        if ($minPasswordLength < 1) {
            return $constraints;
        }

        $constraints[] = new Length(min: $minPasswordLength);

        return $constraints;
    }

    /**
     * Already hashed values are passed through, everything else is treated as plain text and hashed.
     * Scoped fields (customer, admin) use the configured password hasher of their scope.
     */
    private function hash(#[\SensitiveParameter] string $value, PasswordField $field): string
    {
        $scope = $field->getFor();
        if ($scope !== null && $this->passwordHasherRegistry->has($scope)) {
            $hasher = $this->passwordHasherRegistry->get($scope);

            return $hasher->isHashed($value) ? $value : $hasher->hash($value);
        }

        if (password_get_info($value)['algo'] !== null) {
            return $value;
        }

        return password_hash($value, $field->getAlgorithm(), $field->getHashOptions());
    }
}
