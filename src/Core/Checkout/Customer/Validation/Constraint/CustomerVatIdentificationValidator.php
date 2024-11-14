<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation\Constraint;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

#[Package('checkout')]
class CustomerVatIdentificationValidator extends ConstraintValidator
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public function validate(mixed $vatIds, Constraint $constraint): void
    {
        if (!$constraint instanceof CustomerVatIdentification) {
            throw new UnexpectedTypeException($constraint, CustomerVatIdentification::class);
        }

        if ($vatIds === null) {
            return;
        }

        if (!is_iterable($vatIds)) {
            throw new UnexpectedValueException($vatIds, 'iterable');
        }

        $vatIdPattern = $this->getVatIdPattern($constraint);
        if ($vatIdPattern === null) {
            return;
        }

        foreach ($vatIds as $vatId) {
            if (!preg_match($vatIdPattern, (string) $vatId)) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ vatId }}', $this->formatValue($vatId))
                    ->setCode(CustomerVatIdentification::VAT_ID_FORMAT_NOT_CORRECT)
                    ->addViolation();
            }
        }
    }

    private function getVatIdPattern(CustomerVatIdentification $constraint): ?string
    {
        $vatIdInformation = $this->connection->fetchAssociative(
            'SELECT check_vat_id_pattern, vat_id_pattern FROM `country` WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($constraint->getCountryId())]
        );

        if ($vatIdInformation === false) {
            return null;
        }

        \assert(\array_key_exists('check_vat_id_pattern', $vatIdInformation));
        \assert(\array_key_exists('vat_id_pattern', $vatIdInformation));

        if (!$constraint->getShouldCheck() && !$vatIdInformation['check_vat_id_pattern']) {
            return null;
        }

        $pattern = (string) $vatIdInformation['vat_id_pattern'];
        if ($pattern === '') {
            return null;
        }

        if (!Feature::isActive('v6.7.0.0')) {
            return '/^' . $pattern . '$/i';
        }

        return '/^' . $pattern . '$/';
    }
}
