---
title: Use plugin upgrade version, if available, to check for plugin conflicts
author: Julian Drauz
author_email: julian.drauz@pickware.de
author_github: @DrauzJu
---
# Core
* Changed `Shopware\Core\Framework\Plugin\Requirement\RequirementsValidator` to use plugins upgrade version instead of 
  installed version to check for conflicts. This is important when updating a plugin, because in this case the new
  upgrade version is relevant, not the previously installed version.
