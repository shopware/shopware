---
title: Allow conversion of units in the rule builder
issue: NEXT-17050
---
# Core
* Added constant `UNIT_LENGTH` in `src/Core/Framework/Rule/RuleConfig.php`
* Added constant `UNIT_TIME` in `src/Core/Framework/Rule/RuleConfig.php`
___
# Administration
* Added component `sw-condition-unit-menu` in `src/Administration/Resources/app/administration/src/app/component/rule/sw-condition-unit-menu/index.js`
* Added new directive `v-click-outside`
* Added new utils function `convertUnit` to convert different units like kg, meters cubic meters in `src/Administration/Resources/app/administration/src/module/sw-settings-rule/utils/unit-conversion.utils.spec.js`
* Changed function `stringRepresentation` in `src/Administration/Resources/app/administration/src/app/component/form/sw-number-field` to convert scientific notation to decimal numbers
* Changed component `sw-condition-generic` in `src/Administration/Resources/app/administration/src/app/component/rule/condition-type/sw-condition-generic` to implement unit conversion
* Changed mixin `generic-condition` in `src/Administration/Resources/app/administration/src/app/mixin/generic-condition.mixin.js` to implement unit conversion
