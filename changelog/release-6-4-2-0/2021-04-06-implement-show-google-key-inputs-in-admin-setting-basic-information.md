---
title: Implement show google key inputs in admin setting basic information
issue: NEXT-14136
---
# Core
* Deprecated `sw-settings-captcha-select` component in `src/Core/System/Resources/config/basicInformation.xml`.
* Added new `Captcha` card in `src/Core/System/Resources/config/basicInformation.xml`.
___
# Administration
* Added new `sw-settings-captcha-select-v2` component
    * This component allows users to define active captchas and save site key, secret key of google reCaptcha via `Settings -> Basic information`
* Deprecated `sw-settings-captcha-select` component.
