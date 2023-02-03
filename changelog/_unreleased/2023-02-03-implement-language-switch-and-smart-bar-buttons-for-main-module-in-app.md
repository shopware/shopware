---
title: Implement language switch and smart bar buttons for main module in App
issue: -
author: Vu Le
author_email: vu.le@shapeandshift.dev
author_github: crisalder2806
---
# Administration
* Added `smartBarButtonAdd` message handler in `app/init/main-module.init.ts` to handle adding language switch and smart buttons from `Admin Extension SDK`
* Added `sw_extension_sdk_module_smart_bar_buttons`, `sw_extension_sdk_module_language_switch` blocks in `sw-extension-sdk/page/sw-extension-sdk-module/sw-extension-sdk-module.html.twig` to render language switch and smart bar buttons added by `Admin Extension SDK`
* Changed `sw-extension-component-section/sw-extension-component-section.html.twig` to render tabs inside `card` component section 
