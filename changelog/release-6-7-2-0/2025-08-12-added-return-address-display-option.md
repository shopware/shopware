---
title: Added document return address display option
issue: 11534
author: Simon Fiebranz
author_email: s.fiebranz@shopware.com
author_github: @CR0YD
---
# Administration
* Added option `displayReturnAddress` in `sw-settings-document-detail/index.js` to toggle the display of the return address above the customer address in documents.
___
# Upgrade Information
## Return address in documents
The option `Display company` address in the `Company settings` section of the document configuration is now split into `Display return address` and `Display company address`.  
The former toggles the display of the return address above the customer address in the address block.  
The latter toggles the display of the company address below the header on the right-hand side of the document.
