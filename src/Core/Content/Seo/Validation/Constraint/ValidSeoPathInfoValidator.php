<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\Validation\Constraint;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Seo\SeoException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * @internal
 */
#[Package('inventory')]
class ValidSeoPathInfoValidator extends ConstraintValidator
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidSeoPathInfo) {
            throw SeoException::unexpectedType($constraint, ValidSeoPathInfo::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        if (!\is_string($value)) {
            $this->context->buildViolation(ValidSeoPathInfo::INVALID_TYPE_MESSAGE)
                ->addViolation();

            return;
        }

        if (ValidSeoPathInfo::containsDisallowedCharacters($value)) {
            $this->context->buildViolation($constraint->getMessage())
                ->setParameter('{{ path }}', $this->formatValue($value))
                ->setCode(ValidSeoPathInfo::INVALID_CHARACTERS)
                ->addViolation();
        }

        if (preg_match('#^https?://.+#i', $value) !== 1) {
            return;
        }

        $foundExternalStorefrontDomain = $this->connection->fetchOne(
            'SELECT 1 FROM `sales_channel_domain` WHERE `is_external_storefront` = 1 AND :url LIKE CONCAT(`url`, \'%\')',
            ['url' => $value],
        );

        if ($foundExternalStorefrontDomain !== false) {
            return;
        }

        $this->context->buildViolation(ValidSeoPathInfo::INVALID_DOMAIN_MESSAGE)
            ->setParameter('{{ path }}', $this->formatValue($value))
            ->setCode(ValidSeoPathInfo::INVALID_DOMAIN)
            ->addViolation();
    }
}
