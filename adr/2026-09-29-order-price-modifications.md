---
title: Represent order-level price modifications and settlements outside of line items
date: 2026-09-29
area: checkout
tags: [checkout, cart, order, promotion, tax, voucher, payment, plugin, after-sales]
---

## Context

An order's total can only be changed by line items today.
Everything that adjusts the total without being a product or a delivery — a voucher, a goodwill reduction, a payment fee — is therefore modelled as an `order_line_item`, usually of type `promotion`, `credit` or a plugin-specific type.
A line item always flows through `AmountCalculator` like a product: its price is part of `positionPrice`, its taxes are part of `calculatedTaxes`, and in a gross cart `netPrice` is derived as `totalPrice - calculatedTaxes`.

That is correct for a *discount*, which lowers the value of what was supplied and therefore the tax base.
It is wrong for a *means of payment*, which lowers only the amount still due while the tax base stays the same.
The EU VAT Directive (2006/112/EC, as amended by Directive (EU) 2016/1065, in particular Articles 30a and 30b) distinguishes single-purpose and multi-purpose vouchers. For the Shopware calculation model, the architecture therefore needs to support:

* A **single-purpose voucher** (SPV), whose VAT is due on its issuance and every transfer (Art. 30b(1)); the later handing over of the goods is not an independent transaction. When it is redeemed, the part of the supply covered by the voucher has already been taxed and must not be taxed again in the order. The order's VAT is therefore reduced at the voucher's rate. This is calculated like a discount restricted to that rate, but it is not a discount: the value of the supply is unchanged, part of it was taxed earlier. A promotional voucher given away free of charge, by contrast, is a genuine discount.
* A **multi-purpose voucher** (MPV), for example a shop-wide gift card, whose redemption must not reduce the taxable value of the supplied goods and services; it settles part of the amount due.

For an MPV, a line item always produces a wrong tax result or a wrong net amount (cases 1 and 2 below).
For an SPV, a line item is only partly right: with a single tax rate, the proportional reduction of case 1 gives the correct number, but a credit line item is not capped (case 3), and in a cart with mixed tax rates the reduction must be restricted to the voucher's rate instead of being distributed across all rates.

### Reproducible cases on trunk

All cases use a gross sales channel, one product at 119.00 EUR with 19 % tax and horizontal tax calculation.
The correct result for an MPV of 50.00 EUR is: net 100.00, tax 19.00, total 119.00, amount due 69.00.

1. **Voucher redeemed as a Shopware promotion (absolute discount of 50.00 EUR).**
   `DiscountAbsoluteCalculator` distributes the discount proportionally across the affected tax rates through `AbsolutePriceCalculator`.
   Result: total 69.00, tax 11.02, net 57.98.
   For an MPV, the calculated VAT is 7.98 EUR below the VAT on the full supplied value. (For an SPV at 19 %, this result happens to be correct.)
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
The author of this ADR is one of the CEOs of Net Inventors.
The problems come from EasyCoupon support cases; the proposed model is meant to be usable by any voucher, gift card or fee extension, not only by EasyCoupon.

## Options

### Option A: a tax-exempt marker on the existing promotion / credit line item

Add a flag that makes `AmountCalculator` skip the line item's tax and exclude it from the net amount.
The voucher stays a position in every document, cart response and ERP export, `netPrice` stops being derived from `totalPrice` for one kind of line item only, and every consumer that sums line items would have to learn the flag.
It breaks `total = net + tax` in the same way as Option E.

### Option B: price processors without persistence

Introduce cart extension points for order-level adjustments, but persist nothing beyond `order.price`.
It fixes the cart calculation, but cannot survive `RecalculationService`, cannot explain the order total to documents or integrations, and cannot be audited.

### Option C: the voucher as a partial payment (split payment)

Redeem an MPV as its own `order_transaction`, next to the transaction of the customer's payment method.
Legally this is the most accurate model, since an MPV is a means of payment.
Orders can already hold several transactions, and `PaymentProcessor` pays the newest open one. The remaining gaps are limited: `TransactionProcessor` always creates one full-amount transaction, `SetPaymentOrderRoute` cancels the other transactions and recreates the full amount, and the e-invoice paid amount only considers the primary transaction.
On its own, C does not cover taxable adjustments (SPVs, fees, merchant reductions), which change the value of the supply and belong to the price.

### Option D: existing extension mechanisms

Registering a `shopware.cart.processor`, decorating `Processor` or `AmountCalculator`, or overwriting `Cart::price` afterwards. Cases 2 and 3 are the result of these workarounds; several extensions cannot contribute side by side, and nothing is recorded on the order.

### Option E: one persisted modification entity for taxable and tax-exempt adjustments

The original proposal of this ADR: a single `order_price_modification` entity whose `tax_rules` distinguishes tax-exempt (`NULL`), proportionally taxable (empty) and rate-restricted (non-empty) rows, applied inside `CartPrice`.
It models an MPV as part of the price even though it is a means of payment, and pays for that by breaking `total = net + tax`. Almost every consequence of E follows from that single break: a dedicated document block, an OpenAPI caveat, required changes in headless frontends, mail templates that a migration cannot fix, and a changed meaning of `CartAmountRule`. It also needs a write lock and several calculation invariants only to keep the tax-exempt rows consistent.

### Option F: taxable adjustments as additional costs, MPVs as settlements outside the price (decided)

Split the problem along the legal distinction:

* **Taxable adjustments** (SPVs, fees, merchant reductions) stay part of the price, as additional costs next to shipping costs in `AmountCalculator`. They are not positions.
* **MPVs** stay out of the price entirely and are recorded as **settlements** of the amount due: `amountDue = totalPrice - Σ settlements`.

`CartPrice` keeps its meaning, `total = net + tax` holds everywhere, and the settlement side follows Option C.

## Decision

We choose **Option F**.
The settlement side and the code claim handler are decided. For the price side, the decision that taxable adjustments stay in the price is settled; whether they are represented as additional costs or as line items is still open (see "Additional costs or line items").
Existing promotions keep their current line-item representation; this ADR adds new, opt-in representations and does not migrate promotions.
As a consequence, this decision does not change case 1 for shops that keep redeeming MPVs as promotions. Case 1 is resolved only when an MPV is redeemed as a settlement instead of a promotion, either by a voucher extension or, later, by an option in the promotion system itself. Whether promotions should be able to act as settlements is out of scope of this ADR.

### Price side: order price modifications

A **price modification** is a taxable, order-level adjustment that is neither a product nor a delivery.

* In the cart, modifications are transient `PriceModifier`s in `Cart::priceModifiers`. They are applied after all line items and deliveries are calculated, after every `shopware.cart.processor`.
* They are passed to `AmountCalculator` as additional costs, exactly like `$shippingCosts`: they change `netPrice`, `calculatedTaxes` and `totalPrice`, and never `positionPrice`.
* A modification is either distributed proportionally across all tax rates of the cart, or restricted to the listed tax rates (an SPV at one rate). A reduction is capped so that the amount it applies to cannot go below zero per affected tax rate; this prevents case 3. Surcharges are not capped. Distribution and rounding use the existing price calculators, as for promotions.
* A modification states whether it is a discount or the redemption of an already taxed SPV. The calculation is the same, but documents and ERP exports can show the difference.
* Percentage modifications store their percentage and target (goods and/or shipping); the definition is authoritative on recalculation.
* On placement, modifications are persisted as `order_price_modification` rows on the order aggregate (versioned with the order), with `label`, `price`, `price_definition`, `tax_rules`, `position`, `type` (contributing extension, `NULL` for manual rows), `referenced_id`, `payload` and `custom_fields`.
* After placement, the persisted rows are the source of the order's modifications on recalculation; price processors of extensions are not asked again for carts that originate from an order. Merchants can add, edit and delete rows in the Administration like other order data.

#### Additional costs or line items

Taxable adjustments could also stay line items, as today. Most of the technical gaps can be closed within the line-item model: a plugin line item type with its own processor can restrict a reduction to one tax rate and cap it like `DiscountAbsoluteCalculator`; processors with a priority below -5000 run after `DeliveryProcessor` and can cap against goods plus shipping, as `PromotionDeliveryProcessor` already does; and recalculation can keep the stored `price`, `priceDefinition` and `payload` by detecting `OrderConverter::ORIGINAL_ID`, as `PromotionCollector` does.

Additional costs are proposed instead because an order-level adjustment is not a position:

* it would no longer appear in the positions table of documents, change `positionPrice`, or have to be mapped as an item by ERP exports;
* a merchant reduction after placement would not need a fake position;
* several extensions could contribute through one typed extension point instead of each implementing its own line item type, processor priority and recalculation handling.

The line-item model has the advantage that line items plus shipping still add up to the total, so payment integrations that validate item sums need no change for taxable adjustments, and no new entity is required.
This trade-off is **open for discussion**: if line items are preferred, the price side of this decision is reduced to documenting the patterns above for extensions, and only the settlement side and the code claim handler remain.

### Payment side: settlements

A **settlement** is a means of payment that reduces the amount due without changing the price, typically an MPV.

* In the cart, settlements are a transient collection `Cart::settlements`, outside of `CartPrice`. The cart exposes `amountDue = totalPrice - Σ settlements`. Settlements are capped at the amount due; they never make it negative.
* `amountDue` is a cart value only: the amount to be charged with the customer's payment method at placement. It is not persisted on the order. After placement, the amount still open follows from the order's transactions, as today; after a regular payment it is zero.
* On placement, every settlement becomes its own `order_transaction` in a paid state, referencing the contributing extension. `TransactionProcessor` creates the transaction for the customer's payment method over `amountDue` instead of `totalPrice`.
* `SetPaymentOrderRoute` and other code that replaces the open transaction must keep settlement transactions and recreate only the remaining amount due.
* **Consistency at placement.** The contributing extension charges the voucher balance during order placement. If that fails, for example because a parallel order has already used the balance, order placement fails like for a product that is out of stock: neither the order nor a paid settlement transaction is written. Charging must be idempotent (for example keyed by the settlement transaction id), so that a retry cannot charge twice. Protecting the balance against concurrent redemptions is the responsibility of the extension; how core embeds the charge into placement is an implementation detail.
* A voucher redeemed after placement is an additional settlement transaction; refunding a settlement to the voucher is the responsibility of the contributing extension.
* A settlement transaction records the use of an already paid means of payment, not a new payment: no money is captured, and its paid state means the voucher has been charged. In accounting it offsets the voucher liability.
* Cancelling the order refunds or cancels settlement transactions like other paid transactions; the contributing extension reacts to the transaction state change and restores the voucher balance. For a partial return, the merchant decides which transaction is refunded, as for orders with several transactions today. Changing the payment method affects only the transaction over the remaining amount.

### Extension points

All are tagged services, the same pattern as `shopware.cart.collector` / `shopware.cart.processor`, so several extensions can contribute at the same time:

* `PriceCollectorInterface` (`shopware.cart.price_collector`): loads the data a modification or settlement needs.
* `PriceProcessorInterface` (`shopware.cart.price_processor`, supports `priority`): processors run in descending priority. Each receives the calculated cart and everything earlier processors contributed, so it can cap its own contribution against what remains, and returns only its own price modifiers and settlements. Processors with the same priority run in a deterministic order (for example by service id), so the result does not depend on the order in which extensions were installed. Core does not resolve conflicts between extensions beyond this order; the caps keep the result consistent. The developer documentation lists the priorities used by core.
* `CartCodeClaimHandlerInterface` (`shopware.cart.code_claim_handler`): lets an extension claim a code submitted to the promotion code field. Promotions participate through `PromotionCartCodeClaimHandler`. A code claimed by more than one handler fails with `CartException::ambiguousCodeClaim()`. Unclaimed codes behave as before. Claiming only decides which handler is responsible: a handler must not reserve or consume a code while claiming it; that happens when the order is placed. This extension point is independent of the price model and could be delivered on its own.

## Consequences

### Calculation, rules and APIs

* `total = net + tax` holds for every order. `CartPrice` and its OpenAPI schema keep their meaning.
* `CartAmountRule` sees taxable modifications, like shipping costs, but not settlements: paying with a gift card does not change which rules match.
* The Store API cart response gains `priceModifiers`, `settlements` and `amountDue`. Frontends that ignore them still show a consistent price, but the amount to pay is only correct if they render `amountDue`.
* `order.priceModifications` is `ApiAware`; settlements are available through `order.transactions`.

### Documents and e-invoices

* Taxable modifications appear in the document summary next to shipping costs.
* Settlements appear below the total as "Total / gift card / amount to pay".
* For ZUGFeRD / XRechnung, taxable modifications map to document-level allowances and charges (BG-20/BG-21) with their VAT category, and settlements to the paid amount (BT-113). The paid amount has to consider all paid transactions, not only the primary one.
* The redemption of an already taxed SPV is not necessarily the same as an allowance. Its mapping in ZUGFeRD / XRechnung must be validated separately, both for VAT law and against EN 16931, before the document slice is implemented.

### Storefront and mails

* Cart, checkout, finish page and account order pages show modifications in the summary and settlements with the amount to pay.
* Default mail templates are extended accordingly. Customised templates keep showing a consistent total; they only miss the amount to pay until merchants add the new block, which the release notes describe.

### Payment integrations and ERP

* Nothing changes for orders without modifications or settlements.
* Payment integrations that send an item list must send taxable modifications as discount or surcharge items and charge `amountDue`. Where the provider validates the item sum against the charged amount (for example PayPal, Klarna), settlements have to be sent in the provider's gift card or store credit field. A discount field is only a fallback, because providers may treat it as a price reduction with different tax and accounting effects. This is the main migration effort for integrations and has to be documented.

### Editing after placement

* `RecalculationService` does not update transactions (`setIncludeTransactions(false)`). When a merchant changes a price modification on a paid order, `order.price` changes but the transactions do not, exactly as for every other order edit today. Refunds and additional captures stay manual.
* Invoices already generated are not changed; a correction is documented by a credit note or a new invoice, as for other order edits.
* Recalculation never changes settlement transactions. A change of the order price therefore never changes a redeemed voucher balance; if a merchant wants to give back part of a voucher, that is a refund of the settlement transaction.
* ERP exports take the tax base from `netPrice` and `calculatedTaxes` and the settlement amounts from the order transactions.

### Existing orders and extensions

* Existing orders are not migrated.
* Extensions that decorate `CartLineItemController::addPromotion()` must move to `CartCodeClaimHandlerInterface`.
* Extensions overriding `AmountCalculator::calculate()` have to forward the additional costs.
* The new interfaces are introduced as `@experimental` for one minor cycle. Net Inventors adopts them in EasyCoupon during this phase and reports what needs to change before they become stable.

### Delivery

The implementation is owned by Shopware.
The previous implementation of Option E ([#20955](https://github.com/shopware/shopware/pull/20955), closed) can serve as reference for persistence, recalculation and the code claim handler.
Net Inventors contributes the reproducible cases above as acceptance tests, reviews the implementation from the perspective of a voucher extension, and adopts the new extension points in EasyCoupon.

A possible split into independent deliveries:

1. Code claiming (`CartCodeClaimHandlerInterface`), independent of the rest.
2. Settlements: cart collection, `amountDue`, settlement transactions, `TransactionProcessor` and `SetPaymentOrderRoute` changes.
3. Price modifications: additional costs in `AmountCalculator`, entity, recalculation.
4. Administration, storefront, mails and documents, including the e-invoice mapping.

### Open for discussion

* Whether taxable adjustments are represented as additional costs (as proposed above) or stay line items with documented patterns for capping, rate restriction and recalculation (see "Additional costs or line items").
* Whether settlements need their own payment method per contributing extension, or one generic "settlement" payment method with the extension referenced on the transaction.
* The names `PriceCollectorInterface` and `PriceProcessorInterface` are preliminary; since both also serve settlements, a more neutral name may fit better.
* Whether `PriceProcessorInterface::process()` should take a single value object instead of individual arguments before it becomes stable.
* Whether the processor chain should additionally be published through `ExtensionDispatcher`.
