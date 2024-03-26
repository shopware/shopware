---
title: Change administration node version
issue: NEXT-31592
author: Sebastian Seggewiß
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Changed administration node version from `18 || 19 || 20` to `20`
___
# Upgrade Information
## Administration Node.js version change

To use the administration it's now mandatory that your node version is the current LTS version `20` (`Iron`).
If you use `devenv` or `nvm`, you just need to update your session as our configuration files are configured to use the correct version.
Otherwise, you need to update your node installation manually.
