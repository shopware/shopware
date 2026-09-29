---
title: Represent order-level price modifications outside of line items
date: 2026-09-29
area: checkout
tags: [checkout, cart, order, promotion, tax, voucher, plugin, after-sales]
---

## Context

An order's total can only be changed by line items today.
Everything that adjusts the total without being a product or a delivery — a voucher, a goodwill reduction, a payment fee — is therefore modelled as an `order_line_item`, usually of type `promotion`, `credit` or a plugin-specific type.
A line item always flows through `AmountCalculator` like a product: its price is part of `positionPrice`, its taxes are part of `calculatedTaxes`, and in a gross cart `netPrice` is derived as `totalPrice - calculatedTaxes`.

That is correct for a *discount*, which lowers the value of what was supplied and therefore the tax base.
It is wrong for a *means of payment*, which lowers only the amount still due while the tax base stays the same.
The EU VAT Directive (2006/112/EC, as amended by Directive (EU) 2016/1065, in particular Articles 30a and 30b) distinguishes single-purpose and multi-purpose vouchers. For the Shopware calculation model, the architecture therefore needs to support:

* A **single-purpose voucher** (SPV), whose tax treatment is determined at issuance and whose redemption may reduce the taxable amount represented by the order.
* A **multi-purpose voucher** (MPV), for example a shop-wide gift card, whose redemption must be representable without reducing the taxable value of the supplied goods and services; it settles part of the amount due.

For an MPV, a line item always produces a wrong tax result or a wrong net amount (cases 1 and 2 below).
For an SPV, a line item is only partly right: with a single tax rate, the proportional reduction of case 1 gives the correct number, but a credit line item is not capped (case 3), and in a cart with mixed tax rates the reduction must be restricted to the voucher's rate instead of being distributed across all rates.

### Reproducible cases on trunk

All cases use a gross sales channel, one product at 119.00 EUR with 19 % tax and horizontal tax calculation.
The correct result for an MPV of 50.00 EUR is: net 100.00, tax 19.00, amount due 69.00.

1. **Voucher redeemed as a Shopware promotion (absolute discount of 50.00 EUR).**
   `DiscountAbsoluteCalculator` distributes the discount proportionally across the affected tax rates through `AbsolutePriceCalculator`.
   Result: total 69.00, tax 11.02, net 57.98.
   For the MPV treatment described above, the calculated VAT is 7.98 EUR below the VAT on the full supplied value. (For an SPV at 19 %, this result happens to be correct.)
   This is the only redemption path core offers, so an MPV implemented through promotions cannot preserve the full taxable value of the supplied goods today.

2. **Voucher as a tax-free credit line item (empty `TaxRuleCollection`), the usual plugin workaround.**
   The line item contributes no tax, but its price is still part of the total the net amount is derived from.
   Result: total 69.00, tax 19.00, net **50.00**.
   The tax amount is right, but `order.price.netPrice` claims a tax base of 50.00 for 19.00 EUR of VAT.
   Documents, the order summary and every ERP export that reads `netPrice` or sums line item nets report the wrong tax base.
   In a net sales channel (`taxStatus = net`) the result is the same, because `netPrice` is the sum of all line item prices.

3. **Voucher as a credit line item carrying the product's tax rule, redeemed against a smaller cart.**
   Cart with the product at 30.00 EUR, shipping 4.90 EUR, voucher 50.00 EUR at 19 %.
   Nothing caps a credit line item, so the 19 % rate ends up with tax 4.79 + 0.78 (shipping) − 7.98 = **−2.41 EUR**, and the order total becomes −15.10 EUR.
   This is the "negative tax amount" merchants and customers reported in the order summary.

Promotions cap their discount at the affected goods (`-min(abs($price), $totalOriginalSum)`), which avoids case 3, but not case 1.

### Other limitations of the line-item model

* **After-sales corrections.** A merchant who grants a reduction on a placed order has to add a fake line item. It shows up in the positions table of every document, changes `positionPrice`, and has no field that states whether it reduces the tax base.
* **Recalculation.** Once an order exists, `RecalculationService` rebuilds a cart from the order. A plugin that computed an adjustment from its own state (for example a voucher balance) either has to reproduce it from state that has changed since placement, or loses it.
* **Code input.** `/checkout/promotion/add` treats every submitted code as a promotion code. A plugin that owns its own codes (gift cards, loyalty points, partner codes) has to decorate `CartLineItemController` to intercept it. Two such plugins cannot coexist.

### Motivation

The change was driven by EasyCoupon, a Shopware voucher extension by Net Inventors that issues and redeems both single-purpose and multi-purpose vouchers, and by the difficulty of mapping these voucher types correctly onto orders.
The author of this ADR is one of the CEOs of Net Inventors and worked on the change as a developer.
The problems below come from EasyCoupon support cases; the proposed extension points are designed to be usable by any voucher, gift card or fee extension, not only by EasyCoupon.

The difficulties in detail:

* Voucher types affect shipping costs and tax in fundamentally different ways.
  Depending on the type, either the gross price including tax has to be reduced proportionally, or only the amount due may drop while tax is still calculated on the full, original value — for example a multi-purpose voucher that was already paid for, but whose VAT only becomes due on redemption.
* The line-item representation cannot express this distinction.
  In certain constellations it shows **negative tax amounts** in the order summary (case 3 above), which is confusing for merchants and customers and wrong from an accounting point of view.
* There were many customer requests to take voucher redemptions out of the order's line-item structure, because a voucher is not a position of its own (product, shipping, etc.) but an adjustment of the total.

## Options

### Option A: a tax-exempt marker on the existing promotion / credit line item

*Availability: requires a core change. What extensions can do today is only the workaround of case 2.*

Add a flag, for example `payload.taxExempt` or a new `LineItem` state, that makes `AmountCalculator` skip the line item's tax and exclude it from the net amount.

It cannot:

* keep `positionPrice` and the positions table correct: the voucher still is a position, in every document, every Store API cart response and every ERP line item export;
* stay local to `AmountCalculator`: `netPrice` would stop being derived from `totalPrice` for one kind of line item only, so every consumer that sums line items (`PriceCollection`, delivery/percentage calculators, document renderers, payment integrations sending item lists) would need to learn the flag, or silently disagree with the order total;
* model an after-sales correction that is explicitly *not* a position;
* give a plugin a supported point to apply its adjustment after all line items and shipping are known, which a tax-exempt reduction needs to cap itself against the remaining total.

It is the smallest change, and it fixes case 2 for shops that are willing to change their voucher plugin. It does not fix case 1 unless promotions get the same flag, which would then have to be configured per promotion by the merchant.

### Option B: `PriceProcessorInterface` alone, without a new entity

*Availability: requires a core change. It is a subset of this proposal, without persistence.*

Introduce the cart extension points (`PriceCollectorInterface`, `PriceProcessorInterface`, `PriceModifierResult` with `additionalCosts` and `taxExemptAdjustment`), but persist nothing beyond `order.price`.

It fixes the cart calculation for cases 1–3, if the voucher is redeemed through a plugin instead of a promotion.
It cannot:

* survive `RecalculationService`: once an order is edited in the Administration, the adjustment has to be recomputed from plugin state that may have changed (voucher balance already consumed, plugin deactivated) — the order total silently changes or the voucher is applied twice;
* explain the order: `order.price` would contain a total that is not `netPrice + tax`, and nothing in the order says why. Documents, the order detail page, ERP exports and payment integrations have no data to render or reconcile;
* offer after-sales corrections by the merchant;
* be audited: the amount, the tax treatment and the contributing plugin at the time of placement are not recorded anywhere.

Storing the same data in an order extension or custom field would make it persistable, but untyped, not versioned with the order, not editable in the Administration and without a validation point.

### Option C: the voucher as a partial payment (split payment)

*Availability: requires a core change, larger than this proposal.*

Redeem an MPV as its own `order_transaction` with a voucher payment method, next to the transaction of the customer's payment method.
Legally this is the most accurate model, since an MPV is a means of payment.

It cannot:

* be done on the current payment model: an order has one active transaction, and payment handlers, the transaction state machine, refunds, the order state derived from the transaction, and documents all assume that;
* express taxable adjustments (SPVs, merchant discounts, fees), which change the value of the supply and therefore belong to the price, not to the payment;
* express after-sales reductions by the merchant.

Multiple parallel transactions would be a larger architecture change than this proposal, affecting every payment integration, and would still need a price-side model for everything except MPVs.

### Option D: existing extension mechanisms

*Availability: possible today without any core change; this is the status quo. An extension can register its own `shopware.cart.processor`, decorate `Processor` or `AmountCalculator`, or overwrite `Cart::price` after the calculation through the existing `CheckoutCartRuleLoaderExtension`. Cases 2 and 3 are the result of these workarounds.*

* **`shopware.cart.processor`** runs before the amount is calculated and can only change line items and deliveries. Anything it adds ends up as a line item or as shipping costs, so it cannot change only the amount due.
* **An event or `Extension` after the amount calculation** (see [Transition to an Event-Based Extension System](./2024-06-18-extended-event-system.md)) could overwrite `Cart::price`. But several listeners would each replace the whole `CartPrice` without a typed result per contributor, without the running state a reduction needs to cap itself against what earlier contributors already deducted, and without any record of what was applied — so the order could neither display nor persist the individual adjustments, and nothing could be reapplied on recalculation.

This proposal therefore uses tagged services with a typed result (`PriceModifierResult`), the same pattern `Processor` already uses for `shopware.cart.collector` and `shopware.cart.processor`.
**Open for discussion:** the chain could additionally be published through `ExtensionDispatcher`, so that the whole modification step can be observed or replaced like other cart extension points.

### Option E: a persisted `order_price_modification` entity plus the cart extension points (proposed)

*Availability: requires a core change; this proposal.*

The full model described below.
It is the largest change: a new entity on the order aggregate, three extension points, and a new component to be rendered wherever an order total is rendered.
It cannot be introduced without integrations that reconstruct the order total from line items (see Consequences) having to read one more association.

## Decision

We introduce **order price modifications** as the representation for order-level adjustments that must not be represented as products or deliveries (Option E).
Existing promotions keep their current line-item representation; this ADR adds a second, opt-in representation and does not migrate promotions.

`order_price_modification` represents an adjustment to the amount payable when Shopware cannot express the adjustment through its current transaction model. It therefore includes both genuine price adjustments and settlement adjustments. It is not intended to redefine a voucher as legally being a price adjustment.

### Data model

`order_price_modification` belongs to the order aggregate:

| Field | Meaning |
|---|---|
| `id`, `version_id` | Versioned together with the order (draft order edits). |
| `order_id`, `order_version_id` | Required. `ON DELETE CASCADE` with the order. |
| `label`, `description` | Shown to customers and merchants. |
| `price` | Signed gross amount. Negative reduces the total, positive surcharges it. |
| `price_definition` | `absolute` or `percentage` (with `target`: goods and/or shipping). `NULL` for manual rows. For `percentage`, `price` is only the value at placement; `price_definition` is authoritative. |
| `tax_rules` | `NULL`: **tax-exempt** (changes only the amount due). Empty collection: **taxable**, distributed proportionally across every tax rate of the base. Non-empty: taxable, restricted to the listed rates. |
| `position` | Order of application within the same tax treatment. |
| `type` | Technical name of the contributing extension. `NULL` means added manually. |
| `referenced_id` | Stable identifier within `type`. |
| `payload` | Opaque data of the contributing extension (for example the redeemed voucher code). Never read by core. |
| `custom_fields` | As usual. |

`tax_rules` is stored by a new, reusable `TaxRuleCollectionField`.

In the cart, the same concept is a transient `PriceModifier` in `Cart::priceModifiers`. It is purely descriptive; the monetary effect is already contained in `Cart::price`.
`CartTransformer` persists the cart's modifiers as `order_price_modification` rows on order placement.

### Calculation invariants

1. **Position.** Modifications are applied after all line items and all deliveries are calculated, in `Processor`, after every `shopware.cart.processor`.
2. **Taxable before tax-exempt.** All taxable modifications are applied first, ordered by `position`, then all tax-exempt ones against what remains.
3. **Taxable modifications** go through `AmountCalculator` as `additionalCosts`: they change `netPrice`, `calculatedTaxes` and `totalPrice` exactly like shipping costs do, and never change `positionPrice`.
4. **Tax-exempt modifications** change only `totalPrice` and `rawTotal`. `netPrice`, `calculatedTaxes` and `taxRules` keep describing the full value of the goods and services. `rawTotal` includes them because it is the unrounded amount payable from which the rounded `totalPrice` is derived.
   Consequently, `totalPrice = netPrice + calculatedTaxes + Σ tax-exempt modifications` (in the gross, net and tax-free states alike; in the tax-free state `calculatedTaxes` is zero).
   This is the only situation in which `totalPrice ≠ netPrice + tax`, and the gap is always fully explained by persisted rows.
5. **Caps.** A reduction is capped so that the amount it is applied to cannot go below zero — per target tax rate for a restricted taxable row, and against the remaining total for a tax-exempt row. When `Processor` has to cap the aggregate of tax-exempt reductions, it trims the listed modifiers (last first), so the listed modifiers always add up to what was actually deducted. Surcharges are not capped.
6. **Rounding.** Taxable amounts are rounded by the existing price calculators. Tax-exempt amounts are rounded with `CashRounding` (item rounding per row, total rounding for the aggregate), since they bypass those calculators.
7. **Single source of truth after placement.** As soon as a cart originates from an order (`OrderConverter::ORIGINAL_ID`), the persisted rows are reapplied by `OrderPriceModificationCollector`/`OrderPriceModificationProcessor`, and processors extending `AbstractOrderAwarePriceProcessor` stop contributing. A plugin seeds an order once, at placement; afterwards only the rows — including merchant edits — determine the total.
8. **Percentage rows.** `price_definition` is authoritative. Percentage rows are recomputed on recalculation from their frozen `percentage` and `target` against the order's current goods and shipping; the persisted `price` stays the value at placement and is not updated.

### Write invariants: the lock

After placement, a row is either **system-contributed** (`type` is not `NULL`) or **manual** (`type` is `NULL`).
`OrderPriceModificationTaxLockValidator` (`PreWriteValidationEvent`) enforces:

* `type` cannot be changed on an existing row (`CHECKOUT__ORDER_PRICE_MODIFICATION_TYPE_LOCKED`).
* `tax_rules` cannot be changed on an existing system-contributed row (`CHECKOUT__ORDER_PRICE_MODIFICATION_TAX_LOCKED`), including switching between tax-exempt (`NULL`) and taxable. Values are compared semantically, so resending an unchanged value is allowed.
* Everything else stays writable, and any row can be deleted. A replacement for a deleted system row has to be added as a new, visibly manual row.

Why exactly these two fields:

* **`tax_rules` is the tax decision.** For a system-contributed row it records a fact that was settled outside the order: an SPV was taxed at a specific rate when it was issued, an MPV was deliberately not taxed at issuance. Changing the treatment afterwards re-declares VAT for a transaction that already happened elsewhere, for example on the gift card sale, and would make the order disagree with the plugin's own records. No after-sales use case requires it.
* **The amount is not a tax decision.** Changing `price`, `label`, `description` or `position` of a row is a legitimate after-sales operation (partial voucher reversal, correcting a fee), and the recalculation derives correct tax from it under the unchanged treatment. Locking the amount would force merchants to delete and re-add rows for routine corrections, which loses provenance instead of protecting it.
* **`type` protects the lock itself.** It is what marks a row as system-contributed. If it were writable, one write clearing `type` would turn the row into a manual one, and a second write could change `tax_rules` unchecked.
* **Deletion stays allowed**, because cancelling a voucher or waiving a fee must remain possible, and a deleted row cannot misstate anything. The audit trail is the order version history; the replacement row is distinguishable because its `type` is `NULL`. The lock does not prevent a merchant from deliberately correcting the accounting representation; it prevents a system-contributed fact from being silently rewritten while retaining its system provenance.
* **Manual rows are not locked**, because the merchant who created them owns the tax decision and may have to correct it.
* **`payload` and `referenced_id` are not locked**, because core never reads them; they belong to the contributing extension, which can validate them itself.

The Administration additionally disables editing of system-contributed rows entirely; the validator is the guarantee for every other writer of the Admin API.

### Extension points

All three are registered as tagged services, the same pattern as `shopware.cart.collector` / `shopware.cart.processor`, so several extensions can contribute at the same time:

* `PriceCollectorInterface` (`shopware.cart.price_collector`, supports `priority`): loads the data a modification needs into `CartDataCollection`.
* `PriceProcessorInterface` (`shopware.cart.price_processor`, supports `priority`): receives prices, shipping costs and everything earlier processors already contributed, and returns a `PriceModifierResult` with its own contribution only.
* `CartCodeClaimHandlerInterface` (`shopware.cart.code_claim_handler`): lets an extension claim a code submitted to the promotion code field. Promotions participate through `PromotionCartCodeClaimHandler`. A code claimed by more than one handler fails with `CartException::ambiguousCodeClaim()` instead of silently picking one. Unclaimed codes behave as before.

`AbstractOrderAwarePriceProcessor` implements invariant 7 for extensions.

## Consequences

### Order documents

* Invoices and the other order documents list the modifications in the summary, next to net, tax and total (`document_sum_price_modifiers` block), because with a tax-exempt row net + tax no longer add up to the total. Without that block the document would look like a calculation error.
* Credit notes refer to the credited line items only and do not show the order's modifications (the block is empty in `credit_note.html.twig`); a credit note does not re-grant a voucher.
* **Open:** the ZUGFeRD / XRechnung builders do not map modifications yet. EN 16931 has the matching concepts — document-level allowances and charges (BG-20/BG-21) with a VAT category for taxable rows, and the paid amount (BT-113) for tax-exempt redemptions. Until this mapping exists, an e-invoice for an order with modifications must not be generated silently wrong; the document slice has to either map them or reject the document.

### Store API and headless frontends

* `/store-api/checkout/cart` returns the new `priceModifiers` collection on the cart. For carts without modifications the response only gains an empty collection.
* With a tax-exempt modification, `price.totalPrice` differs from `price.netPrice + calculatedTaxes`. Headless frontends that compute or validate the total from these fields must render the modifications. The OpenAPI schema has to document both.
* `order.priceModifications` is `ApiAware` and available through the usual association loading on the Store API and Admin API order endpoints.
* `CartLineItemController` answers an unknown code directly with `checkout.promotion-not-found` instead of adding a placeholder promotion line item and recalculating the cart. The message is unchanged.

### Rules

`Processor::process()` applies the modifications after all cart processors (line items, promotions, deliveries) and after the amount calculation, but before the cart validators and `TransactionProcessor`.
`CartRuleLoader` evaluates rules against the fully processed cart and recalculates until the matching rules and `totalPrice` are stable.
Rules therefore see the prices after all modifications:

* `CartAmountRule` compares `price.totalPrice` and **is affected** by taxable and tax-exempt modifications.
* `CartPositionPriceRule` (`positionPrice`) and `GoodsPriceRule` (goods line items) are not affected.
* Shipping costs calculated by price use the delivery positions (`DeliveryCalculator::CALCULATION_BY_PRICE`) and are calculated before the modifications, so they are not affected.
* The tax-free threshold for customers and countries (`CartRuleLoader::validateTaxFree()`) uses `positionPrice` and is not affected.

Example: a 100.00 EUR cart paid with an 80.00 EUR gift card has a cart amount of 20.00 EUR, so a rule "cart amount ≥ 50.00 EUR" no longer matches, which can switch off a payment method such as invoice or a promotion bound to that rule.
Merchants who mean the value of the goods can use the goods or position price rules instead.

### Mails, existing orders and after-sales

* **Mail templates** are stored in the database and are often edited by merchants, so a migration cannot update them. With a tax-exempt modification, an order confirmation mail that shows net, tax and total without the modifications looks like a calculation error. The storefront slice updates the default templates to list `order.priceModifications`, and its release notes tell merchants how to add the same block to customised templates.
* **Existing orders** are not migrated. Credit and promotion line items in existing orders stay line items.
* **Editing after payment.** `RecalculationService` does not update transactions (`setIncludeTransactions(false)`). When a merchant changes a modification on a paid order, `order.price` changes but the transaction amount does not, exactly as for every other order edit today. Refunds and additional captures stay manual.
* **Admin order creation** shows the modifications of the cart read-only. Modifications can only be added, edited or deleted once the order exists. This is a known limitation, not a design constraint.

### ERP and payment integrations

* Nothing changes for orders without modifications. Promotions stay line items, so existing shops keep producing the same data until an extension or a merchant adds a modification.
* Integrations that reconstruct the order total from `lineItems` + `deliveries` must add `priceModifications`. This affects in particular payment integrations that transmit an item list which the provider validates against the amount (for example PayPal, Klarna): they have to send taxable modifications as discount/surcharge items and tax-exempt modifications as a reduction of the amount due (or a gift card / store credit field where the provider supports one).
* ERP exports can take the tax base directly from `netPrice` and `calculatedTaxes`, which are now correct for vouchers; the amount due is `totalPrice`. The contributing extension and its own reference are available in `type`, `referenced_id` and `payload`.
* `TransactionProcessor` already charges `order.price.totalPrice`, so the amount charged is correct without changes.

### Existing extensions

* Promotion plugins that only use the promotion system are not affected.
* Extensions that decorate `CartLineItemController::addPromotion()`, or rely on a placeholder promotion line item being added for an unknown code, must move to `CartCodeClaimHandlerInterface`. `CartLineItemController` is `@internal`; its constructor changes.
* An extension that claims a code which is also an active promotion code now gets an ambiguous-claim error instead of winning silently.
* Extensions overriding `AmountCalculator::calculate()` have to forward the additional-costs input introduced by this change.

### Compatibility promise

* The entity, its fields and their meaning (including `tax_rules` `NULL` = tax-exempt and the lock) are public API from the first release on. Changing the meaning of a persisted field later would change historical orders, so it is only possible through a new field and a migration.
* `PriceCollectorInterface`, `PriceProcessorInterface`, `CartCodeClaimHandlerInterface`, `PriceModifierResult`, `PriceModifier` and `AbstractOrderAwarePriceProcessor` are introduced as `@experimental` for one minor cycle after the first release, so that the first real integrations can still shape them. Net Inventors commits to adopting them in EasyCoupon during this phase and reporting back what needs to change before they become stable. After that they follow the regular backwards compatibility promise: interface methods do not change within a major version, and value objects only gain optional constructor arguments.
* **Open for discussion:** `PriceProcessorInterface::process()` currently takes nine arguments. Bundling them into a value object before the interface becomes stable would let the contract grow without breaking implementers.
* `OrderPriceModificationCollector`, `OrderPriceModificationProcessor` and `OrderPriceModificationTaxLockValidator` are `final` with `@internal` constructors; they are implementations, not extension points.

### Delivery

The problem is live today: many merchants using EasyCoupon see negative tax amounts and wrong net amounts on their orders and documents (cases 2 and 3), and this proposal was written in response to their complaints.
The implementation already exists as a complete, tested change ([#20955](https://github.com/shopware/shopware/pull/20955)).

We therefore propose to deliver it as **one pull request** after this ADR is accepted, restructured so that it can be reviewed slice by slice:

* one commit per slice below, each self-contained, with its own tests that fail without it and its own release notes;
* the pull request is first aligned with the decisions of this ADR, so that feedback on the design is settled here once and does not have to be repeated per slice.

Splitting the same change into six sequential pull requests would add a rebase, CI and review round trip for every slice, although the slices only help merchants together: a persisted modification is not releasable without the lock, the display in documents, or the Administration to correct it.
That would considerably delay the fix for an existing tax reporting problem without changing the design, and makes contributions of this size hard to sustain for an external contributor.
Only as a last resort, if a single pull request could otherwise not be accepted, would a split along the largest boundaries be feasible as a compromise: core (slices 1–3), Administration (4), storefront and documents (5), code claiming (6).

Slices:

1. Persisting a modification: entity, migration, `TaxRuleCollectionField`, `CartTransformer`.
2. Applying it to the total: `PriceCollectorInterface`, `PriceProcessorInterface`, `AmountCalculator` `additionalCosts`, order recalculation.
3. Protecting settled tax: the lock validator.
4. Editing in the Administration: components, ACL, snippets.
5. Showing it to the customer: storefront, cart restore, finish page, mail templates, documents (including the e-invoice decision above).
6. Claiming codes: `CartCodeClaimHandlerInterface`, `PromotionCartCodeClaimHandler`.
