---
title: Move install.lock to var directory
issue: 12398
author: Martin Bens
author_email: m.bens@shopware.com
author_github: @SpiGAndromeda
---
# Core
* Changed `Shopware\Core\Maintenance\System\Command\SystemInstallCommand` to create `install.lock` file in `/var/` directory instead of project root
* Changed `Shopware\Core\Maintenance\System\Command\SystemInstallCommand` to check both `/var/install.lock` and `/install.lock` for backward compatibility
* Changed `Shopware\Core\Installer\Finish\SystemLocker` to create lock file in `/var/install.lock` location
* Changed `public/index.php` to check both `/var/install.lock` and `/install.lock` locations for backward compatibility
