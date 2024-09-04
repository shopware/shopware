---
title: Changed default recipient for mails send via the Flow Builder on the product review send trigger
issue: NEXT-34301
author: Simon Fiebranz
author_email: s.fiebranz@shopware.com
author_github: CR0YD
---
# Core
* Changed the default recipient to the customer in the `ReviewFormEvent`, which is configured and dispatched in the `store-api.product-review.save` route
___
# Upgrade information

## Flow Builder
### `Product review / Send` trigger
We changed the recipient of `product review` mails in all flows that are triggered through the `Product review / Send` trigger to the `Admin`, due to a bug that sent the mails to the `Admin` despite `Customer` being configured as the recipient.  
As our standard product review mail template is a message written for the admin anyway, we decided that those emails should go by default to the `Admin` user.  
But due to the bug being fixed now, the option `Customer` can be selected again, if that's the actual intention for the flow, and works correctly now.
