---
title: Press ESC key in the modal will go back listing page
issue: #10518
author: Le Nguyen
author_email: nguyenquocdaile@gmail.com
author_github: @Le Nguyen
---
# Administration
* Changed `handleKeyDownDebounce` method to check if the event originates from within a modal in `src/app/plugin/shortcut.plugin.js`.
