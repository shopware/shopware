<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Decides whether a company account needs a contact person and adjusts a validation definition
 * and its request data accordingly
 */
#[Package('checkout')]
final class CompanyAccountNameFields
{
    public const CONFIG_SHOW = 'core.loginRegistration.showNameFieldsForCompanyAccounts';
    public const CONFIG_REQUIRED = 'core.loginRegistration.nameFieldsRequiredForCompanyAccounts';

    private const NAME_FIELDS = ['firstName', 'lastName'];

    /**
     * @internal
     */
    public function __construct(private readonly SystemConfigService $systemConfigService)
    {
    }

    public function areVisible(?string $salesChannelId): bool
    {
        return $this->isEnabled(self::CONFIG_SHOW, $salesChannelId);
    }

    public function areRequired(?string $salesChannelId): bool
    {
        return $this->areVisible($salesChannelId) && $this->isEnabled(self::CONFIG_REQUIRED, $salesChannelId);
    }

    /**
     * The account type comes from the request first and the authenticated customer second
     */
    public function areOptional(DataBag $data, ?CustomerEntity $customer, ?string $salesChannelId): bool
    {
        $accountType = $data->get('accountType');

        $isBusinessAccount = \is_string($accountType) && $accountType !== ''
            ? $accountType === CustomerEntity::ACCOUNT_TYPE_BUSINESS
            : ($customer?->isBusinessAccount() ?? false);

        return $isBusinessAccount && !$this->areRequired($salesChannelId);
    }

    /**
     * Drops the blank check on the names. The caller says whether the company has to stand in, because
     * a stored address may already carry a name while a new one needs someone to be named.
     */
    public function relax(DataValidationDefinition $definition, bool $requireCompany): void
    {
        foreach (self::NAME_FIELDS as $property) {
            $constraints = $definition->getProperty($property);

            if ($constraints === []) {
                continue;
            }

            $definition->set($property, ...array_values(array_filter(
                $constraints,
                static fn (Constraint $constraint) => !$constraint instanceof NotBlank
            )));
        }

        if ($requireCompany) {
            $definition->add('company', self::companyNotBlank());
        }
    }

    /**
     * A name the form did not fill becomes an empty string, which the data abstraction layer accepts
     * while null is rejected. With $submittedOnly an absent name stays absent, so an update leaves
     * the stored value alone.
     */
    public function normalize(DataBag $data, bool $submittedOnly = false): void
    {
        foreach (self::NAME_FIELDS as $property) {
            if ($submittedOnly && !$data->has($property)) {
                continue;
            }

            if ($data->get($property) === null) {
                $data->set($property, '');
            }
        }
    }

    public static function companyNotBlank(): NotBlank
    {
        return new NotBlank(normalizer: static fn (mixed $value): mixed => \is_string($value) ? trim($value) : $value);
    }

    private function isEnabled(string $key, ?string $salesChannelId): bool
    {
        $value = $this->systemConfigService->get($key, $salesChannelId);

        return $value === null || (bool) $value;
    }
}
