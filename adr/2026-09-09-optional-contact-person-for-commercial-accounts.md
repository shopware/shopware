---
title: Optional contact person for commercial customer accounts
date: 2026-09-09
area: checkout
tags: [customer, validation, documents, payment]
---

## Context

Registering a commercial account always requires a first and last name, and `Settings > Login & Registration` offers no way to turn that off. Issue [#15321](https://github.com/shopware/shopware/issues/15321) states the case: a legal transaction with a GmbH or an AG is a transaction with a legal entity, and a legal entity has no first and last name. The same issue asks for the company name to become an account level field for commercial customers in the Administration, where today it exists only on the address.

We want the shop to decide. A merchant should be able to set the contact person to required, optional or hidden, and the company name should take over as the identity when there is none.

## Decision

Add two settings that make the contact person flexible for commercial accounts, and one resolved name that every consumer reads instead of concatenating the two columns.

```
core.loginRegistration.showNameFieldsForCompanyAccounts
core.loginRegistration.nameFieldsRequiredForCompanyAccounts
```

Both default to on, so nothing changes until a merchant switches one off. A hidden field is never required, so the first implies the second. The whole feature is gated on `core.loginRegistration.showAccountTypeSelection`: with that off the names stay mandatory and both switches are hidden.

To achieve this we touch the following scopes.

### Data model

`firstName` and `lastName` on `customer`, `customer_address`, `order_customer` and `order_address` get `AllowEmptyString` beside `Required`. `StringFieldSerializer::getConstraints()` then yields `NotNull` instead of `NotBlank`, so an empty string becomes legal while `null` stays rejected. The columns stay `NOT NULL`.

```php
// the same change in all four definitions
(new StringField('first_name', 'firstName'))
    ->addFlags(new Required(), new AllowEmptyString());
```

### Customer identity

`CustomerDefinition` gets a runtime field, filled by a subscriber on `customer.loaded`:

```php
(new StringField('display_name', 'displayName'))->addFlags(new ApiAware(), new Runtime()),

// CustomerDisplayNameSubscriber
$personName = trim($customer->getFirstName() . ' ' . $customer->getLastName());

if ($personName === '' && $customer->getAccountType() === CustomerEntity::ACCOUNT_TYPE_BUSINESS) {
    $customer->setDisplayName(trim($customer->getCompany() ?? ''));

    continue;
}

$customer->setDisplayName($personName);
```

The company stands in only when there is no person name, so a commercial account that has a contact person keeps showing that person.

### Validation

`RegisterRoute`, `ChangeCustomerProfileRoute`, `UpsertAddressRoute` and `CheckoutConfirmPageLoader` resolve the effective account type from the request first and the authenticated customer second, because the profile and address forms omit it whenever the account type selection is hidden. Once the names are optional the company gains a `NotBlank` on the write paths, with a trimming normalizer guarded by `is_string()` because `HappyPathValidator` calls normalizers without checking the type. The confirm page relaxes the names but does not require a company, so an address stored before the setting was switched on cannot block checkout.

```php
$accountType = $data->get('accountType') ?: $customer->getAccountType();

if ($accountType === CustomerEntity::ACCOUNT_TYPE_BUSINESS && !$config->areNamesRequired($salesChannelId)) {
    // keep every constraint on the names except NotBlank
    $config->makeNamesOptional($definition);
    $definition->add('company', new NotBlank(normalizer: $trimIfString));
}
```

### Orders and documents

`CustomerTransformer` writes the company into the order snapshot when there is no person name. `ZugferdDocument` and `TradePartyView` move to one shared formatter:

```php
$personName = trim($firstName . ' ' . $lastName);
$company = trim($company ?? '');

if ($company === '' || $company === $personName) {
    return $personName;
}

return $personName === '' ? $company : $personName . ' - ' . $company;
```

### Mail

The shipped templates move to the resolved name, and a migration carries that to installations that never edited them. `MailUpdate` only rewrites a template while `updated_at IS NULL` on both the template and its translation, so a shop that customised its mails keeps its own text. Twenty fixture files across five templates read the customer name today, in three shapes. Two of them never read `firstName`, so a company placed there would not appear in the mail at all.

```twig
{# before #}
Hello {{ customer.firstName }} {{ customer.lastName }},
Hello {{ customer.salutation.translated.letterName }} {{ customer.lastName }},
Hello {{ customer.salutation.translated.letterName }} {{ customer.firstName }} {{ customer.lastName }},

{# after #}
Hello {{ customer.displayName }},
Hello {{ customer.salutation.translated.letterName }} {{ customer.displayName }},
```

The recipient name is built in the events, not the template. Ten `MailAware`
customer events join the two columns in `getMailStruct()`, so without a change
the `To:` header of a nameless company account is a single space. All ten read
the resolved name instead:

```
Shopware\Core\Checkout\Customer\Event\
    CustomerRegisterEvent
    CustomerLoginEvent
    CustomerLogoutEvent
    CustomerDeletedEvent
    CustomerPasswordChangedEvent
    CustomerAccountRecoverRequestEvent
    CustomerDoubleOptInRegistrationEvent
    CustomerGroupRegistrationAccepted
    CustomerGroupRegistrationDeclined
    DoubleOptInGuestOrderEvent
```

```php
// before, in each of the ten
public function getMailStruct(): MailRecipientStruct
{
    return new MailRecipientStruct([
        $this->customer->getEmail() => $this->customer->getFirstName() . ' ' . $this->customer->getLastName(),
    ]);
}

// after
public function getMailStruct(): MailRecipientStruct
{
    return new MailRecipientStruct([
        $this->customer->getEmail() => $this->customer->getDisplayName(),
    ]);
}
```

`MailStorer` builds the same name twice when it restores a flow, without even a
space between the two parts, and reads the resolved name too.

Order events keep their own join. `OrderStateMachineStateChangeEvent`,
`OrderPaymentMethodChangedEvent` and `CheckoutOrderPlacedEvent` read
`orderCustomer`, which already carries the company through the snapshot. The two
newsletter events and `UserRecoveryRequestEvent` address a recipient that is not
a customer at all.

There is no runtime patching of the customer for the render. A shop that customised a mail template keeps addressing `customer.firstName` and `customer.lastName`, and gets an empty greeting for an account with no contact person. That is the trade we accept: the shop owns that template, and hiding the change behind a subscriber would mean every mail renders a customer that does not match the one in the database.

### Storefront

The name fields follow the account type `<select>` through storefront JavaScript that toggles the required rule with `window.formValidation.setFieldRequired()` and `setFieldNotRequired()`. `FormFieldTogglePlugin` is not reused because it disables what it hides, which drops the values from the payload. The sidebar and the account overview read `customer.displayName`.

```twig
{# server rendered starting state #}
validationRules: personNameRequired ? 'required' : ''

{# identity, instead of firstName ~ ' ' ~ lastName #}
{{ context.customer.displayName }}
```

```js
// the visitor can switch type without a reload, so the marker has to follow
accountTypeSelect.addEventListener('change', () => nameFields.forEach((field) => {
    isCompanySelected() && !namesRequired
        ? window.formValidation.setFieldNotRequired(field)
        : window.formValidation.setFieldRequired(field);
}));
```

### Administration

The two name fields become optional only when both settings allow it. `Customers > New customer` gains a company field in the account section for the commercial type, so the company is persisted on the customer and not only on the address. This is a requirement of its own in the issue, not a side effect of the name change. The customer and order lists read the resolved name.

```js
// sw-customer-base-form, starts strict until the settings resolve
contactPersonRequired() {
    return !this.isBusinessAccountType || this.companyNamesRequired;
}
```

```twig
<mt-text-field :required="contactPersonRequired" v-model="customer.firstName" />

<mt-text-field v-if="isBusinessAccountType" v-model="customer.company"
               :required="!contactPersonRequired" />
```

## Consequences

What the API accepts after this change:

* An empty string on `firstName` and `lastName` for `customer`, `customer_address`, `order_customer` and `order_address`, on every write path. That includes the Admin API, the Sync API and direct repository writes, and it applies to private accounts too. An entity extension sees the field definition, never the row or the configuration, so the relaxation cannot be conditional. `null` is still rejected.
* `customer.displayName` in store API and Admin API responses, resolved per account type. It is a runtime field, so it cannot be sorted or searched by. The Administration customer list keeps sorting on `lastName,firstName`, which means a commercial account without a contact person displays as its company but sorts as an empty name. Search still finds it through `company`.
* Two new system config keys, both defaulting to on.
* The Administration needs [#20173](https://github.com/shopware/shopware/pull/20173) before it can save an empty name at all.

What the checkout domain should be aware of:

* An empty given name can now reach a payment provider. `AbstractPaymentHandler::pay()` receives only a `PaymentTransactionStruct` and a `Context`, and handlers load the order themselves to build the payload from `orderCustomer` and `addresses`. Core's own payment code never reads a name, so every case lives in an integration outside this repository. `payer.name.given_name` in PayPal is the one we would check first. If a provider rejects the payload the customer has already clicked pay, and Core cannot recover from that.
* `order_customer` has no `accountType` column, so it cannot resolve a display name the way `customer` does. Copying the company into `firstName` keeps documents and the order list working, at the cost of a column named `firstName` holding a company name.

Those two decide each other. If providers accept an empty name, the snapshot can stay honest and `order_customer` gets its own runtime field. If they reject it, the company copy stays and the shared formatter is mandatory.
