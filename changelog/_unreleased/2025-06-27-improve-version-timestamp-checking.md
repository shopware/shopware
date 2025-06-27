---
title: Improve version timestamp checking
issue: NEXT-00000
author: Jasper Peeters
author_email: jasper.peeters@meteor.be
author_github: JasperP98
---

# Core
* Changed: Added a merge block (`Shopware\Core\Framework\DataAbstractionLayer\VersionManager::checkVersionTimestamps`) when a version is trying to merge that is older than the live version
