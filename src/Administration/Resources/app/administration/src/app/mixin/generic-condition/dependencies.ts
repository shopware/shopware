/**
 * @sw-package framework
 *
 * Everything generic-condition mixin needs from outside its mini-module.
 */
/** @private */
export type { default as Criteria } from '@shopware-ag/meteor-admin-sdk/es/data/Criteria';
/** @private */
export { defineComponent } from 'vue';
/** @private */
export { default as createCriteriaFromArray } from 'src/app/service/criteria-helper.service';
/** @private */
export type { default as RuleConditionService } from 'src/app/service/rule-condition.service';
/** @private */
export { useRuleConditionsConfigStore } from 'src/app/store/rule-conditions-config';
/** @private */
export { default as convertUnit } from 'src/module/sw-settings-rule/utils/unit-conversion.utils';

/** @private */
export const cloneDeep = ((...args: unknown[]) =>
    (Shopware.Utils.object.cloneDeep as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.object.cloneDeep;
