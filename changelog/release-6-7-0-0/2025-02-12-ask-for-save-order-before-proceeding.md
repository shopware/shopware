---
title: Ask for save order before proceeding
issue: NEXT-40221
author: Michel Bade
author_email: m.bade@shopware.com
author_github: @cyl3x
---
# Administration
* Added `sw-order-save-changes-beforehand-modal` to ask the user to save his changes before proceeding
* Changed `sw-order-details` to provide the logic to display `sw-order-save-changes-beforehand-modal` and handle the save
* Changed `sw-order-state-history-card`, `sw-order-details-state-card` and `sw-order-general-info` to ask for a save before continuing with a state change
