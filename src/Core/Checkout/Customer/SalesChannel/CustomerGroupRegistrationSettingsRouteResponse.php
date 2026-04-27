<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<Struct>
 */
#[Package('checkout')]
class CustomerGroupRegistrationSettingsRouteResponse extends StoreApiResponse
{
    private CustomerGroupEntity $registration;

    /**
     * @param CustomerGroupEntity $object
     */
    public function __construct(
        Struct $object,
    ) {
        \assert($object instanceof CustomerGroupEntity);

        $this->registration = $object;

        /** @var array<string, mixed> $payload */
        $payload = $object->getVars();
        $translated = $payload['translated'] ?? [];

        if (!\is_array($translated)) {
            $translated = [];
        }

        $registrationOnlyCompanyRegistration = $translated['registrationOnlyCompanyRegistration'] ?? false;
        $translated['registrationOnlyCompanyRegistration'] = (bool) $registrationOnlyCompanyRegistration;

        $payload['translated'] = $translated;
        $payload['registrationOnlyCompanyRegistration'] = (bool) $registrationOnlyCompanyRegistration;

        /** @var ArrayStruct<array<string, mixed>> $response */
        $response = new ArrayStruct($payload, $object->getApiAlias());

        parent::__construct($response);
    }

    public function getRegistration(): CustomerGroupEntity
    {
        return $this->registration;
    }
}
