---
title: Fix Commercial Update
issue: #6919
---
# Core
* Added service alias `Shopware\Core\Content\Mail\Service\MailerTransportLoader` for `Shopware\Core\Content\Mail\Transport\MailerTransportLoader` to ensure the commercial plugin does not error during the update process.
___
# Upgrade Information
## Fix `ServiceNotFoundException` during platform update

Updating shopware to 6.6.10.0 with the commercial plugin activated lead to `ServiceNotFoundException` being thrown until the commercial plugin was updated as well. 
To fix the error an alias to the old service was added to ensure the commercial plugin does not error during the update process.