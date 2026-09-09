---
title: Optional contact person for commercial customer accounts
date: 2026-09-09
area: checkout
tags: [customer, validation, documents, payment]
---

## Context

A commercial customer often has no single contact person. Purchasing runs through a shared mailbox, orders are placed by whoever is on shift, and the company name is the identity that matters. Shopware requires `firstName` and `lastName` on every customer, so those shops type `-`, `.`, or the company name twice to get past the register form. Issue [#15321](https://github.com/shopware/shopware/issues/15321) asks for the contact person to become optional.

The awkward part isn't the validation. It's that `firstName` and `lastName` are the de facto identity of a customer across the whole system. Roughly nine places in Core branch on `accountType === 'business'` already, and every one of them renders, sorts, mails, or prints a name built from those two columns. Relaxing the validation without deciding what takes their place produces blank greetings, blank invoice buyers, and blank rows in the Administration.

Two settings drive the feature, both in `Settings > Login & Registration` next to the existing account type selection:

- `core.loginRegistration.showNameFieldsForCompanyAccounts`
- `core.loginRegistration.nameFieldsRequiredForCompanyAccounts`

Both default to on, so nothing changes for an existing shop until one is switched off. A hidden field is never required, so `showNameFieldsForCompanyAccounts = false` implies the second one is off too.

## Customer data model

The four entities that store a contact person are `customer`, `customer_address`, `order_customer`, and `order_address`. All four keep `firstName` and `lastName` as `Required` with `NOT NULL` columns.

**Problems:**

* `StringFieldSerializer` turns an empty string into `null` for a `Required` string field, and the column rejects `null`. An empty name cannot be written at all.
* Nothing in the system answers "what is this customer called" in one place. Each consumer concatenates the two columns itself.

**Solution:**

Add `AllowEmptyString` next to `Required` on all four name fields. `StringFieldSerializer::getConstraints()` then produces `NotNull` instead of `NotBlank`, so an empty string is a legal value and `null` still isn't. The columns stay `NOT NULL`.

Add a runtime field to `CustomerDefinition` that resolves the name a consumer should render:

```php
// CustomerDefinition::defineFields()
(new StringField('display_name', 'displayName'))->addFlags(new ApiAware(), new Runtime()),
```

A subscriber on `customer.loaded` fills it:

```php
public function onCustomerLoaded(EntityLoadedEvent $event): void
{
    foreach ($event->getEntities() as $customer) {
        $personName = trim($customer->getFirstName() . ' ' . $customer->getLastName());

        if ($personName === '' && $customer->getAccountType() === CustomerEntity::ACCOUNT_TYPE_BUSINESS) {
            $customer->setDisplayName(trim($customer->getCompany() ?? ''));

            continue;
        }

        $customer->setDisplayName($personName);
    }
}
```

The company stands in only when there is no person name. A commercial account that does have a contact person keeps showing that person, which is why an existing shop sees no change.

The field is deliberately runtime rather than a stored column. A stored column would be sortable and searchable, but it needs a migration, a backfill, and write-path logic on four entities, and it drifts the moment anything writes a name outside the DAL. The cost of the runtime choice is stated under Consequences.

## Validation and the account type gate

Validation lives in `RegisterRoute`, `ChangeCustomerProfileRoute`, `UpsertAddressRoute`, and the confirm page, all of which build their definitions from `AddressValidationFactory` and `CustomerProfileValidationFactory`.

**Problems:**

* The relaxation depends on the account type of the request, which the profile and address forms omit whenever the account type selection is hidden. Reading the submitted value alone puts a commercial customer on the private branch, where `ChangeCustomerProfileRoute` rejects an empty contact person and clears the stored company and VAT ids.
* `UpsertAddressRoute` writes both names from the request every time. A form that hides them submits neither, so the write either fails on `null` or replaces a stored contact person with an empty string.
* Core treats every `accountType` value that is not `business` as the private branch and adds no `Choice` constraint, so an unknown value must count as private too.

**Solution:**

Resolve the effective account type from the request first and from the authenticated customer second. When the request omits the company on the profile route, read it back from the customer so the save neither fails nor wipes it. When it omits a name on an address update, read it back from the stored address.

Once the names are optional the company becomes the identity, so it gains a `NotBlank` with a trimming normalizer on the write paths. The normalizer needs an `is_string()` guard, because `HappyPathValidator` calls normalizers without checking the type.

The confirm page validates addresses that already exist, so it relaxes the names but does not require a company. Adding that requirement there would block checkout for any commercial customer whose address predates the setting being switched on.

The whole feature is gated on `core.loginRegistration.showAccountTypeSelection`. With the selection off, both switches are hidden in the Administration and the names stay mandatory. One boolean decides it, which keeps the number of states a reader has to hold in their head down to something workable.

That gate has a known hole. `hasSelectedBusiness` comes from the customer group setting `registrationOnlyCompanyRegistration`, which is independent of the global account type selection. A shop with the selection off and a company-only registration group produces commercial accounts that the feature does not reach. Those shops have to enable the account type selection to use it. Widening the gate later is a change to one condition.

## Orders, documents and payments

This is the domain we would most like feedback on, and the one where the blast radius is hardest to see from the customer side.

An order keeps its own copy of the customer in `order_customer`. That copy has no `accountType` column, so it cannot answer the same question the runtime field answers for `customer`.

**Problems:**

* `ZugferdDocument::withBuyerInformation()` and `TradePartyView::buyerFromOrder()` each build the buyer name inline as `firstName lastName` and then append `- company`. Neither trims, and neither checks whether the company already equals the person name. `TradePartyView` raises a violation when the result is empty, so a nameless commercial buyer fails e-invoice generation outright.
* Payment integrations read `firstName` and `lastName` from the order customer or the billing address and send them on. An empty given name may be rejected by the provider rather than by Shopware.

**Solution:**

`CustomerTransformer` writes the company into the snapshot when the person name is empty, so the order carries a buyer name that documents and mails can read. Both document renderers then go through one shared formatter that trims and de-duplicates:

```php
$personName = trim($firstName . ' ' . $lastName);
$company = trim($company ?? '');

if ($company === '' || $company === $personName) {
    return $personName;
}

return $personName === '' ? $company : $personName . ' - ' . $company;
```

Two open questions for the checkout domain:

1. Should `order_customer` get its own runtime display name instead of the snapshot copy? A twin field keeps the snapshot columns honest, at the cost of a second subscriber and a second definition of the same rule. The snapshot copy is simpler but puts the company in a column named `firstName`, which reads oddly to anything inspecting the order directly.
2. Which payment integrations forward the customer name to a provider, and which of those providers reject an empty given name? PayPal's payer object is the case we are most unsure about. The answer decides whether an empty name is acceptable at all on a paid order, or whether the company has to be substituted before the payment call.

## Storefront and Administration

The forms and the identity displays are separate problems, and the second one is easy to miss.

**Problems:**

* `address-personal.html.twig` marks both names required from configuration alone, with no knowledge of the account type the visitor picked. The account type is a runtime `<select>`, so a server-rendered flag cannot answer per type.
* The account sidebar greeting and the account overview render `context.customer.firstName` and `lastName` directly. For a nameless commercial account the greeting reads as though the shop forgot the customer.
* The Administration customer form marks both names required unconditionally, and `sw-customer-create` validates the company on the address without copying it to the account.

**Solution:**

The storefront template reads the resolved settings through a Twig function backed by the same service the routes use, so the form and the backend cannot disagree about what an unsaved setting means. A small storefront plugin follows the account type `<select>` and toggles the required rule through `window.formValidation.setFieldRequired()` and `setFieldNotRequired()`, which handle `data-validation`, `aria-required`, and the required label together. `FormFieldTogglePlugin` is not reused, because it disables what it hides and the values would drop out of the payload.

Identity displays use the runtime field: `{{ context.customer.displayName }}` in the sidebar and the account overview, and the same value in the Administration customer and order lists.

The Administration name fields become optional only when both settings allow it, and default to required until the settings resolve, so a slow request leaves the form strict rather than permissive.

## Extendability

`customer.displayName` is `ApiAware`, so it appears in `/store-api/account/customer` and in Admin API responses without any extra work. Headless clients get the resolved name instead of reimplementing the rule.

An app or plugin that wants a different rule subscribes to `customer.loaded` at a later priority and overwrites the field. A B2B extension could show the organisation unit instead of the company, or prefix the company with a customer number, without touching any consumer.

The two settings are ordinary system config, so a plugin can read them through `SystemConfigService` and follow the same states in its own forms.

## Consequences

### For the platform

The data abstraction layer stops being the guard for an empty contact person. Any write path that has to reject one now says so itself, through the store API validation definitions. That includes the Admin API and direct repository writes, which accept an empty name for private accounts too.

Sorting and searching keep working on the stored columns. The Administration customer list column is `dataIndex: 'lastName,firstName'`, and a runtime field cannot be sorted or searched by, so a nameless commercial account displays as its company but sorts as an empty name and clusters at one end of the list. Search still finds those customers through `company`, which carries `SearchRanking::HIGH`. We accept this rather than pay for a stored column.

The Administration changeset generator needs a matching change, tracked in [#20173](https://github.com/shopware/shopware/pull/20173). It rewrites an empty string to `null` for every field, so a field that is `Required` and `AllowEmptyString` cannot be saved from the Administration at all until that lands.

### For third-party developers

Extensions that relied on the data abstraction layer rejecting an empty name have to validate it themselves. Nothing else changes for them while both settings stay on.

Anything that renders a customer name should move to `customer.displayName`. Concatenating `firstName` and `lastName` still compiles and still works for private accounts, but it produces an empty string for a commercial account without a contact person.

Mail templates live in the shop database, so existing templates keep addressing `customer.firstName` and `customer.lastName`. A subscriber on `MailBeforeValidateEvent` swaps in a rendered copy of the customer carrying the company, and patches the recipient name in `getData()` as well, because `MailService::send()` reads that separately from the template data.
