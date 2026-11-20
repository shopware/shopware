---
title: Optional contact person for commercial customer accounts
date: 2026-09-09
area: checkout
tags: [customer, b2b]
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

Both default to on, so nothing changes until a merchant switches one off. A hidden field is never required, so the first implies the second, the same rule the phone number and birthday pairs follow. There is no gate on `core.loginRegistration.showAccountTypeSelection`: the account type of a write comes from the request first and the authenticated customer second, so a registration without a selection is a private one and keeps the names required, while a logged in commercial customer stays one.

To achieve this we touch the following scopes.

### Data model

`firstName` and `lastName` on `customer`, `customer_address`, `order_customer` and `order_address` get `AllowEmptyString` beside `Required`. `StringFieldSerializer::getConstraints()` then yields `NotNull` instead of `NotBlank`, so an empty string becomes legal while `null` stays rejected. The columns stay `NOT NULL`.

The relaxation cannot depend on the sales channel settings, because a field definition sees neither the row nor the configuration, and the Admin API, the Sync API and direct repository writes carry no sales channel. One rule needs none of that and holds on every path through a `PreWriteValidationEvent` subscriber, `CustomerContactPersonSubscriber`: a customer, an address or an order snapshot may not end up naming nobody. Both names may be empty only for a commercial account with a company, for an address of such an account that carries a company, or for an `order_customer` or `order_address` that carries a company, because an order snapshot has no account type to check. A private account keeps needing a contact person everywhere, and the store API routes add the finer, setting driven rule on top.

```php
// the same change in all four definitions, the existing flags stay
(new StringField('first_name', 'firstName'))
    ->addFlags(new ApiAware(), new Required(), new AllowEmptyString(), new SearchRanking(...));
```

### Customer identity

`CustomerDefinition` and `OrderCustomerDefinition` get a runtime field. A subscriber per entity resolves the name on `loaded` and `partial_loaded` and writes it into the field, so that entities and API responses carry it; the getter returns the field as loaded, like every other runtime field. The `Runtime` flag names the source fields, so a partial read that asks for `displayName` pulls them in.

```php
(new StringField('display_name', 'displayName'))
    ->addFlags(new ApiAware(), new Runtime(['firstName', 'lastName', 'company', 'accountType'])),

// CustomerDisplayNameSubscriber, on a hydrated entity
$customer->setDisplayName($this->resolve(
    $customer->getFirstName(),
    $customer->getLastName(),
    $customer->getCompany(),
    $customer->isBusinessAccount()
));

// on a partial entity, only when the read asked for all four sources
$customer->assign(['displayName' => $this->resolve(...)]);

private function resolve(string $firstName, string $lastName, ?string $company, bool $isBusinessAccount): string
{
    $personName = trim($firstName . ' ' . $lastName);

    if ($personName !== '' || !$isBusinessAccount) {
        return $personName;
    }

    return trim($company ?? '');
}
```

The company stands in only when there is no person name, so a commercial account that has a contact person keeps showing that person. `order_customer` has no account type, so its rule is the person name, else the company.

### Validation

The rule is both a configuration rule and an account type rule. Trunk decides pure configuration rules inside `AddressValidationFactory` and `CustomerProfileValidationFactory`, and account type rules in the routes, because the factories only see the `SalesChannelContext` while the account type comes from the request during registration and on a profile switch. So the factories stay as they are and one injected service, `CompanyAccountNameFields`, does the adjustment once per route: `areOptional()` resolves the effective account type from the request first and the authenticated customer second, `makeNamesOptional()` filters `NotBlank` out of the name constraints and leaves every other rule of the factory in place, and `normalize()` turns an absent name into the empty string the data abstraction layer accepts. Replacing a factory constraint after the fact follows the precedent of the zipcode rule those routes already `set()`.

```php
if ($this->companyAccountNameFields->areOptional($data, $customer, $context->getSalesChannelId())) {
    $this->companyAccountNameFields->makeNamesOptional($validation, requireCompany: true);
    $this->companyAccountNameFields->normalize($data, submittedOnly: true);
}

// makeNamesOptional, per name field
$definition->set($property, ...array_filter(
    $definition->getProperty($property),
    static fn (Constraint $constraint) => !$constraint instanceof NotBlank
));
```

`RegisterRoute`, `ChangeCustomerProfileRoute` and `UpsertAddressRoute` call it. `requireCompany` follows the path: a registration and a profile update demand one, an address demands one only while it is being created, and the confirm page demands none. `UpsertAddressRoute` also requires the customer to be a commercial one, because a private customer cannot make the names of an address optional when the checkout later judges it by the account. `CheckoutConfirmPageLoader` makes the names optional but does not require a company, so an address stored before the setting was switched on cannot block checkout.

On an update with optional names, `UpsertAddressRoute` takes an omitted `firstName`, `lastName` or `company` from the stored address, so a request that sends only some fields cannot clear the name of a company address by accident.
Without optional names nothing changes, and an omitted company is stored as `null` as before.

### Orders and documents

The order snapshot keeps the names as the customer had them. The company already lives in `order_customer.company`, and the display name resolves at read time, so no name column holds a company. `ZugferdDocument` and `TradePartyView` build the buyer name from the snapshot themselves, where the company belongs next to the contact person; the entity stays plain data and does not carry the rule:

```php
$personName = trim($customer->getFirstName() . ' ' . $customer->getLastName());
$company = trim($customer->getCompany() ?? '');

$name = match (true) {
    $company === '' => $personName,
    $personName === '' || $personName === $company => $company,
    default => $personName . ' - ' . $company,
};
```

### Mail

The shipped templates move to the resolved name, and two migrations carry that to installations that never edited them. `MailUpdate` only rewrites a template while `updated_at IS NULL` on both the template and its translation, so a shop that customised its mails keeps its own text.

Seven customer templates and twenty seven order templates greet a recipient by name. The customer ones came in two shapes and keep the tone each one had:

```twig
{# a formal salutation reads the last name, so it keeps it and only falls back #}
{{ customer.salutation.translated.letterName }} {{ customer.lastName|trim ?: customer.displayName }},

{# a greeting that printed the whole person name reads the resolved one #}
Hello {{ customer.firstName }} {{ customer.lastName }},    {# before #}
Hello {{ customer.displayName }},                          {# after #}
```

The fallback is not decoration. `letterName` plus `lastName` renders "Mr Smith", so putting the display name there would greet every private customer as "Mr John Smith". The last name stays where it is and the resolved name steps in only when there is none.

The order templates all greet the same way, so they take the resolved name straight:

```twig
{% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ ' ' }}{% endif %}{{ order.orderCustomer.displayName }},
```

The recipient name is built in the events, not the template. Thirteen `MailAware` events plus `MailStorer` joined the two columns in `getMailStruct()`, so without a change the `To:` header of a nameless company account is a single space. Ten read the customer, three read the order:

```
Shopware\Core\Checkout\Customer\Event\
    CustomerRegisterEvent, CustomerLoginEvent, CustomerLogoutEvent, CustomerDeletedEvent,
    CustomerPasswordChangedEvent, CustomerAccountRecoverRequestEvent,
    CustomerDoubleOptInRegistrationEvent, CustomerGroupRegistrationAccepted,
    CustomerGroupRegistrationDeclined, DoubleOptInGuestOrderEvent

Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent
Shopware\Core\Checkout\Order\Event\OrderPaymentMethodChangedEvent
Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent
```

```php
// before
return new MailRecipientStruct([
    $this->customer->getEmail() => $this->customer->getFirstName() . ' ' . $this->customer->getLastName(),
]);

// after
return new MailRecipientStruct([
    $this->customer->getEmail() => $this->customer->getDisplayName(),
]);
```

There is no runtime patching of the customer for the render. A shop that customised a mail template keeps addressing `customer.firstName` and `customer.lastName`, and gets an empty greeting for an account with no contact person. That is the trade we accept: the shop owns that template, and hiding the change behind a subscriber would mean every mail renders a customer that does not match the one in the database.

### Storefront

The name fields follow the account type `<select>` through a dedicated `CompanyNameFieldsPlugin` that toggles the required rule with `window.formValidation.setFieldRequired()` and `setFieldNotRequired()`, and hides and disables the fields when the setting hides them, so a hidden name is not submitted and a stored one survives an edit. `FormFieldTogglePlugin` is not reused for two reasons: one select carries one toggle configuration in its data attributes and the account type select already drives the company fields, and the "shown but optional" state needs the required marker to follow the select while the fields stay visible, which that plugin only does together with visibility. The template reads the two settings straight from `config()`, like the phone number pair. The sidebar and the account overview read `customer.displayName`.

```twig
{# server rendered starting state #}
validationRules: personNameRequired ? 'required' : ''

{# identity, instead of firstName ~ ' ' ~ lastName #}
{{ context.customer.displayName }}
```

```js
// the visitor can switch type without a reload, so the state has to follow
const isCompany = this._select.value === this.options.businessValue;
const shown = !isCompany || this.options.shown;
const required = !isCompany || (this.options.shown && this.options.required);

this.el.classList.toggle(this.options.hiddenCls, !shown);

this._fields.forEach((field) => {
    shown ? field.removeAttribute('disabled') : field.setAttribute('disabled', 'disabled');

    required
        ? window.formValidation.setFieldRequired(field)
        : window.formValidation.setFieldNotRequired(field);
});
```

### Administration

The two name fields become optional only when both settings allow it. One API service, `companyAccountNameFieldsService`, mirrors `areRequired()` for the sales channel of the customer through the internal action route `GET /api/_action/customer/company-account-name-fields`, and only the three pages that save a customer call it: the create page, the detail page and the order customer modal. The base form and the card receive the answer as a prop and stay strict until it arrives. The company field is required for every commercial account there, whether or not the contact person is, because a commercial account in the Administration is created by a merchant who knows the company. Every read only place, the customer list, the order list, the search bar, the dashboard and the tag assignments, reads `displayName` from the API response instead of resolving the rule again in JavaScript.

The detail card already edits the account level company; the create page did not, so a customer created there carried the company on the billing address only. `Customers > New customer` gains the field in the account section for the commercial type, so the company is persisted on the customer from the start. This is a requirement of its own in the issue, not a side effect of the name change. A migration copies the company from the default billing address into accounts the Administration created without one.

```js
// sw-customer-base-form
props: { companyNamesRequired: { type: Boolean, default: true } },
contactPersonRequired() {
    return !this.isBusinessAccountType || this.companyNamesRequired;
}
```

```twig
<mt-text-field :required="contactPersonRequired" v-model="customer.firstName" />

<mt-text-field v-if="isBusinessAccountType" v-model="customer.company" required />
```

## Consequences

What the API accepts after this change:

* An empty string on `firstName` and `lastName` for `customer`, `customer_address`, `order_customer` and `order_address`, on every write path, `null` still rejected. `CustomerContactPersonSubscriber` keeps the one rule that needs no configuration: both names may only be empty for a commercial account with a company, for an address of such an account with a company, or for an order snapshot with a company. A private account without a contact person is rejected on the Admin API and the Sync API as well.
* `customer.displayName` and `orderCustomer.displayName` in store API and Admin API responses. They are runtime fields, so they cannot be sorted or searched by. The Administration customer list sorts the name column on `lastName,firstName,company`, so commercial accounts without a contact person come first and are ordered by their company. Search finds them through `company`.
* Two new system config keys, both defaulting to on, and no gate on the account type selection. A logged in commercial customer can have an optional contact person while the selection is off.
* A migration that copies the company of the default billing address into the account of a commercial customer that has none.

What extensions have to do, as the commercial plugin did:

* An entity that copies the names of a customer or an address, such as a subscription or an approval snapshot, needs `AllowEmptyString` on its own `firstName` and `lastName`.
  Without it, saving such a copy for a company account without a contact person fails.
* Code that builds its own address validation from `AddressValidationFactory`, for example a copy of the confirm page loader, does not get optional names for free.
  `CompanyAccountNameFields` is internal, so only first party code can call it.
  Third party code has to remove the blank check for company accounts itself, or keeps blocking those accounts.
* A `MailAware` event that names the recipient should use `getDisplayName()` instead of joining the two names, like the core events above.
* A template override that copies a core greeting or name output should call `parent()` or read `displayName`, so it follows the core rule instead of printing two empty names.
