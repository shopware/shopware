---
title: Tax calculation on credit note for customer group with net prices is wrong
issue: NEXT-16922
---
# Core
* Changed method `generate` at `Shopware\Core\Checkout\Document\DocumentGenerator\CreditNoteGenerator` to have a step to check the current price is NET or GROSS to display the correct Net amount and Total Amount in the PDF generated file.
