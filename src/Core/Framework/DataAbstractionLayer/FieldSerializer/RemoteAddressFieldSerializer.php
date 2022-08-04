<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\RemoteAddressField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Will be internal
 */
class RemoteAddressFieldSerializer extends AbstractFieldSerializer
{
    protected const CONFIG_KEY = 'core.loginRegistration.customerIpAddressesNotAnonymously';

    /**
     * @var SystemConfigService
     */
    private $configService;

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
        if (!$field instanceof RemoteAddressField) {
            throw new InvalidSerializerFieldException(RemoteAddressField::class, $field);
        }

        if (!$data->getValue()) {
            return;
        }

        if ($this->configService->get(self::CONFIG_KEY)) {
            yield $field->getStorageName() => $data->getValue();

            return;
        }

        yield $field->getStorageName() => IPUtils::anonymize($data->getValue());
    }

    public function decode(Field $field, $value): ?string
    {
        return $value;
    }
}
