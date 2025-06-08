---
title: Documents Header and Line item tables broken
issue: NEXT-13991
---
# Core
* Changed block `document_line_item_table_row_tax_rate` in `Shopware/Core/Framework/Resources/views/documents/includes/position.html.twig` to remove duplicate line item tax.
* Changed block `document_head` in `Shopware/Core/Framework/Resources/views/documents/base.html.twig` to remove duplicate code.
