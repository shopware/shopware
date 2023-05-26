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
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('core')]
class PasswordFieldSerializer extends AbstractFieldSerializer
{
    public const CONFIG_MIN_LENGTH_FOR = [
        PasswordField::FOR_CUSTOMER => 'core.loginRegistration.passwordMinLength',
        PasswordField::FOR_ADMIN => 'core.userPermission.passwordMinLength',
    ];

    private SystemConfigService $configService;

    /**
     * @internal
     */
    public function __construct(
        ValidatorInterface $validator,
        DefinitionInstanceRegistry $definitionRegistry,
        SystemConfigService $configService
    ) {
        parent::__construct($validator, $definitionRegistry);
        $this->configService = $configService;
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
            $info = password_get_info($value);
            // if no password algorithm is detected, it might be plain text which needs to be encoded.
            // otherwise, passthrough the possibly encoded string
            if (!$info['algo']) {
                $value = password_hash((string) $value, $field->getAlgorithm(), $field->getHashOptions());
            }
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

        if ($minPasswordLength === 0) {
            return $constraints;
        }

        $constraints[] = new Length(['min' => $minPasswordLength]);

        return $constraints;
    }
}
