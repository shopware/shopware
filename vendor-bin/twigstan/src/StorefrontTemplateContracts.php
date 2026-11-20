<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPage;
use Shopware\Storefront\Page\Page;
use Twig\Markup;

/**
 * Analysis entrypoints for reusable and legacy templates without a current core caller.
 * These declare template contracts; they are never registered as routes or executed.
 *
 * @internal
 */
#[Package('framework')]
final class StorefrontTemplateContracts extends StorefrontController
{
    /**
     * @param array{
     *     name: string,
     *     id: string,
     *     label?: string|Markup,
     *     value?: string|int,
     *     checked?: bool,
     *     disabled?: bool,
     *     validationRules?: string,
     *     violationPath?: string,
     *     additionalClass?: string,
     *     additionalInputClass?: string,
     *     additionalLabelClass?: string,
     *     attributes?: array<string, string>,
     *     formViolations?: ConstraintViolationException,
     * } $parameters
     */
    public function radioFields(array $parameters, string|Markup $options): void
    {
        $this->renderStorefront('@Storefront/storefront/component/form/form-radio.html.twig', $parameters);
        $this->renderStorefront('@Storefront/storefront/component/form/form-radio-group.html.twig', $parameters + ['options' => $options]);
    }

    public function legacyCartAlerts(CheckoutCartPage $page): void
    {
        $this->renderStorefront('@Storefront/storefront/component/checkout/cart-alerts.html.twig', ['page' => $page]);
    }

    public function addressActions(CustomerAddressEntity $address, ?CustomerAddressEntity $defaultBillingAddress, ?CustomerAddressEntity $defaultShippingAddress): void
    {
        $this->renderStorefront('@Storefront/storefront/page/account/addressbook/address-actions.html.twig', [
            'address' => $address,
            'defaultBillingAddress' => $defaultBillingAddress,
            'defaultShippingAddress' => $defaultShippingAddress,
        ]);
        $this->renderStorefront('@Storefront/storefront/page/account/addressbook/default-address-actions.html.twig', ['address' => $address]);
    }

    public function legacyFormComponents(string $formPrefix, ?string $forwardTo, ?string $forwardParams): void
    {
        $this->renderStorefront('@Storefront/storefront/element/cms-element-form/form-components/cms-element-form-forward.html.twig', [
            'formPrefix' => $formPrefix,
            'forwardTo' => $forwardTo,
            'forwardParams' => $forwardParams,
        ]);
        $this->renderStorefront('@Storefront/storefront/element/cms-element-form/form-components/cms-element-form-privacy.html.twig');
    }

    public function legacyLogout(Page $page): void
    {
        $this->renderStorefront('@Storefront/storefront/page/account/logout.html.twig', ['page' => $page]);
    }

    public function emptyModal(): void
    {
        $this->renderStorefront('@Storefront/storefront/utilities/modal.html.twig');
    }
}
