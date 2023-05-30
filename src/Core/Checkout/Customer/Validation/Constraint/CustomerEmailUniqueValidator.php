<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation\Constraint;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use function array_filter;

/**
 * @Annotation
 *
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
#[Package('customer-order')]
class CustomerEmailUniqueValidator extends ConstraintValidator
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof CustomerEmailUnique) {
            throw new UnexpectedTypeException($constraint, CustomerEmailUnique::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        $query = $this->connection->createQueryBuilder();

        /** @var array{email: string, guest: int, bound_sales_channel_id: string|null}[] $results */
        $results = $query
            ->select('email', 'guest', 'LOWER(HEX(bound_sales_channel_id)) as bound_sales_channel_id')
            ->from('customer')
            ->where($query->expr()->eq('email', $query->createPositionalParameter($value)))
            ->executeQuery()
            ->fetchAllAssociative();

        $results = array_filter($results, static function (array $entry) use ($constraint) {
            // Filter out guest entries
            if ($entry['guest']) {
                return null;
            }

            if ($entry['bound_sales_channel_id'] === null) {
                return true;
            }

            if ($entry['bound_sales_channel_id'] !== $constraint->getSalesChannelContext()->getSalesChannelId()) {
                return null;
            }

            return true;
        });

        // If we don't have anything, skip
        if ($results === []) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ email }}', $this->formatValue($value))
            ->setCode(CustomerEmailUnique::CUSTOMER_EMAIL_NOT_UNIQUE)
            ->addViolation();
    }
}
