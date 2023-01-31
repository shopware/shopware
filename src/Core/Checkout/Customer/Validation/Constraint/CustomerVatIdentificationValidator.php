<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation\Constraint;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

#[Package('customer-order')]
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

        if (!$this->shouldCheckVatIdFormat($constraint)) {
            return;
        }

        if (!$vatPattern = $this->getVatPattern($constraint)) {
            return;
        }

        $regex = '/^' . $vatPattern . '$/i';

        foreach ($vatIds as $vatId) {
            if (!preg_match($regex, (string) $vatId)) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ vatId }}', $this->formatValue($vatId))
                    ->setCode(CustomerVatIdentification::VAT_ID_FORMAT_NOT_CORRECT)
                    ->addViolation();
            }
        }
    }

    private function shouldCheckVatIdFormat(CustomerVatIdentification $constraint): bool
    {
        if ($constraint->getShouldCheck()) {
            return true;
        }

        return (bool) $this->connection->fetchOne(
            'SELECT check_vat_id_pattern FROM `country` WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($constraint->getCountryId())]
        );
    }

    private function getVatPattern(CustomerVatIdentification $constraint): string
    {
        return (string) $this->connection->fetchOne(
            'SELECT vat_id_pattern FROM `country` WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($constraint->getCountryId())]
        );
    }
}
