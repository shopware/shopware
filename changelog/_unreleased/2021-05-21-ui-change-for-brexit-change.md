---
title: Ui change for brexit changes.
issue: NEXT-14605
flag: FEATURE_NEXT_14114
---
# Administration
* Changed the value of `showSelection` from `false` to `true` in `sw-setting-country-list` for able multiple delete countries in the country setting list screen.
* Added new component is `sw-settings-country-general` which instead for part `General` in `sw-setting-country-detail` used to show the country general data as a tab.
* Added new `sw-number-field` in the `sw-settings-country-general` which used for type the `Min. cart value (Net)` when `Tax free` or `Tax free for companies` is chosen.
* Added new component is `sw-settings-country-state` which instead for part `State` in `sw-setting-country-detail` used to show the country state data as a tab.
* Added new component is `sw-settings-country-currency-dependent-modal` which used for view some currency dependent with `tax-free from base currency`.
* Added new component is `sw-settings-country-currency-hamburger-menu` which used for choose currency for settings.
* Added new props is `autoCloseOutsideClick` with default value is `false` in `sw-context-button/index.js` when `autoCloseOutsideClick` have been set is `sw-context-button` the button will be closed auto when clicked outside the button.
* Added new slot is `customSettings` in `sw-data-grid.html.twig` which used to plug the custom setting for grids.
