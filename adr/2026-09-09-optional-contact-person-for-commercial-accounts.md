---
title: Optional contact person for commercial customer accounts
date: 2026-09-09
area: checkout
tags: [customer, validation, documents, payment]
---

## Context

A commercial customer often has no single contact person. Purchasing runs through a shared mailbox, orders are placed by whoever is on shift, and the company name is the identity that matters. Shopware requires `firstName` and `lastName` on every customer, so those shops type `-`, `.`, or the company name twice to get past the register form. Issue [#15321](https://github.com/shopware/shopware/issues/15321) asks for the contact person to become optional.

Dropping the two `NotBlank` constraints is a two line change. The reason this needs an ADR is what happens next.

`firstName` and `lastName` are the de facto identity of a customer everywhere. The storefront greets with them in `sidebar.html.twig`, the Administration customer list sorts on `lastName,firstName`, mail templates stored in the shop database address them directly, and `ZugferdDocument::withBuyerInformation()` prints them as the e-invoice buyer. Around nine places in Core already branch on `accountType === 'business'`, and each of them builds a name from those two columns on its own. Nothing in the system answers "what is this customer called" in one place.

Two earlier attempts made that concrete. A Core attempt and a plugin attempt both went through several review rounds that kept producing the same two failures: a form that permits what the backend rejects, and a display that goes blank because one consumer was missed. The storefront account greeting rendered as "Hello" with nothing after it for the whole life of one of those branches, invisible to sixty passing unit tests.

So the work is not "make two fields optional". It is "give the system one answer for a customer's name, then make the fields optional".

## Decision

Keep both columns `NOT NULL` and both fields `Required`. Add `AllowEmptyString` beside `Required` on `firstName` and `lastName` for `customer`, `customer_address`, `order_customer` and `order_address`. `StringFieldSerializer::getConstraints()` then yields `NotNull` instead of `NotBlank`, so an empty string becomes a legal value while `null` stays rejected.

Add a runtime field to `CustomerDefinition` that resolves the name a consumer should render, filled by a subscriber on `customer.loaded`:

```php
// CustomerDefinition::defineFields()
(new StringField('display_name', 'displayName'))->addFlags(new ApiAware(), new Runtime()),

// CustomerDisplayNameSubscriber
$personName = trim($customer->getFirstName() . ' ' . $customer->getLastName());

if ($personName === '' && $customer->getAccountType() === CustomerEntity::ACCOUNT_TYPE_BUSINESS) {
    $customer->setDisplayName(trim($customer->getCompany() ?? ''));

    continue;
}

$customer->setDisplayName($personName);
```

The company stands in only when there is no person name, so a commercial account that has a contact person keeps showing that person. An existing shop sees no change.

Two settings sit in `Settings > Login & Registration` beside the account type selection, both defaulting to on:

* `core.loginRegistration.showNameFieldsForCompanyAccounts`
* `core.loginRegistration.nameFieldsRequiredForCompanyAccounts`

A hidden field is never required, so the first implies the second. The whole feature is gated on `core.loginRegistration.showAccountTypeSelection`. With that off, the names stay mandatory and both switches are hidden.

### Scope

**Validation.** `RegisterRoute`, `ChangeCustomerProfileRoute`, `UpsertAddressRoute` and `CheckoutConfirmPageLoader` resolve the effective account type from the request first and the authenticated customer second, because the profile and address forms omit it whenever the account type selection is hidden. Once the names are optional the company gains a `NotBlank` on the write paths, with a trimming normalizer that guards for `is_string()` because `HappyPathValidator` calls normalizers without checking the type. The confirm page relaxes the names but does not require a company, so an address stored before the setting was switched on cannot block checkout.

**Orders and documents.** `CustomerTransformer` writes the company into the order snapshot when there is no person name. Both document renderers move to one shared formatter:

```php
$personName = trim($firstName . ' ' . $lastName);
$company = trim($company ?? '');

if ($company === '' || $company === $personName) {
    return $personName;
}

return $personName === '' ? $company : $personName . ' - ' . $company;
```

**Mail.** A subscriber on `MailBeforeValidateEvent` swaps a rendered copy of the customer into the template data and patches the recipient name in `getData()`, which `MailService::send()` reads separately.

**Storefront.** The name fields follow the account type `<select>` through a small plugin that toggles the required rule with `window.formValidation.setFieldRequired()` and `setFieldNotRequired()`. `FormFieldTogglePlugin` is not reused because it disables what it hides, which drops the values from the payload. The sidebar and the account overview read `customer.displayName`.

**Administration.** The two name fields become optional only when both settings allow it, the account level company becomes editable rather than only copied from the address, and the customer and order lists read the resolved name.

## Consequences

The data abstraction layer stops guarding against an empty contact person. That guard now lives in the store API validation definitions, which means the Admin API and direct repository writes accept an empty name for private accounts too. An entity extension sees the field definition, never the row or the configuration, so the relaxation cannot be made conditional. Extensions that relied on the layer rejecting an empty name have to validate it themselves.

Sorting and searching stay on the stored columns. A runtime field cannot be sorted or searched by, and the Administration customer list column is `dataIndex: 'lastName,firstName'`, so a commercial account without a contact person displays as its company but sorts as an empty name and clusters at one end of the list. Search still finds it through `company`, which carries `SearchRanking::HIGH`. We take this over a stored column, which would need a migration, a backfill, write path logic on four entities, and would drift the moment anything writes a name outside the DAL.

The feature needs [#20173](https://github.com/shopware/shopware/pull/20173) to work in the Administration at all. The changeset generator rewrites an empty string to `null` for every field, so a field that is both `Required` and `AllowEmptyString` cannot be saved from the Administration until that lands.

The gate has a known hole. `hasSelectedBusiness` comes from the customer group setting `registrationOnlyCompanyRegistration`, which is independent of the global account type selection. A shop with the selection off and a company only registration group produces commercial accounts the feature does not reach. Those shops have to enable the account type selection. Widening the gate later is a change to one condition.

### Risks we want the checkout domain to weigh in on

Payment integrations are the part we are least able to judge. `AbstractPaymentHandler::pay()` receives a `PaymentTransactionStruct` and a `Context`, and handlers load the order themselves to build the provider payload from `orderCustomer` and `addresses`. Core's own payment code never reads a name, so every case lives in an integration outside this repository. Several provider APIs carry a payer object with a given and family name, and some reject an empty value or use it for name matching in 3-D Secure and fraud scoring. PayPal's `payer.name.given_name` is the case we would check first.

If a provider rejects the payload, the customer has already clicked pay. Core cannot recover from that, so the answer changes the design rather than adding a patch:

* Providers accept an empty name. The order snapshot can then stay honest and `order_customer` gets its own runtime display name.
* Providers reject it. The snapshot has to carry a non-empty name, so the company keeps being copied into `firstName` and the shared document formatter is mandatory.
* Mixed. Payment handlers need a shared way to resolve a fallback name before building the payload, which is a Core API addition nobody has scoped.

The second risk is smaller and related. `order_customer` has no `accountType` column, so it cannot answer the same question the runtime field answers for `customer`. Copying the company into `firstName` keeps documents and the order list working, at the cost of a column named `firstName` holding a company name, which reads oddly to anything inspecting an order directly.
