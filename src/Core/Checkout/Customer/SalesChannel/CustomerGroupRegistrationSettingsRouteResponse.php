<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct<array<string, mixed>>>
 */
#[Package('checkout')]
class CustomerGroupRegistrationSettingsRouteResponse extends StoreApiResponse
{
    public function __construct(
        private readonly CustomerGroupEntity $registration,
    ) {
        $payload = $registration->getVars();
        $translated = $payload['translated'] ?? [];

        if (!\is_array($translated)) {
            $translated = [];
        }

        $registrationOnlyCompanyRegistration = $translated['registrationOnlyCompanyRegistration'] ?? false;
        $translated['registrationOnlyCompanyRegistration'] = (bool) $registrationOnlyCompanyRegistration;

        $payload['translated'] = $translated;
        $payload['registrationOnlyCompanyRegistration'] = (bool) $registrationOnlyCompanyRegistration;

        parent::__construct(new ArrayStruct($payload, $registration->getApiAlias()));
    }

    public function getRegistration(): CustomerGroupEntity
    {
        return $this->registration;
    }
}
