---
title: Fix ScriptLoader loading invalid cache paths
author: Benjamin Wittwer
author_email: Discord.Benjamin@web.de
author_github: gecolay
---
# Core
* Changed `Shopware\Core\Framework\Script\Execution\ScriptLoader` to no longer load a invalid app twig scripts cache path from the cached app-scripts state, if the cache path for the app twig scripts has been changes in the meantime
