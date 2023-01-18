---
title: Fix administration table editor toolbar for non Chromium user
issue: NEXT-24995
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Administration
* Changed `sw-text-editor-table-toolbar::getNode` so it refers to `anchorNode` of the selection instead of Chromium only `baseNode`
