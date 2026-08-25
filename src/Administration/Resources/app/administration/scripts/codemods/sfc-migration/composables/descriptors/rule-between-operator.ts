/**
 * @sw-package framework
 * @private
 */

import { type ComposableDescriptor, refMembers } from '../types';

const RULE_BETWEEN_OPERATOR_DESCRIPTOR: ComposableDescriptor = {
    id: 'rule-between-operator',
    mixinNames: ['rule-between-operator'],
    import: { source: 'src/app/composables/use-rule-between-operator', name: 'useRuleBetweenOperator' },
    members: refMembers([
        'isBetween',
        'betweenValue',
    ]),
    propArgs: ['condition'],
    // The mixin created the condition's value through the host before writing the pair back.
    callbackArgs: [
        { name: 'ensureValueExist', kind: 'callback' },
    ],
};

export default RULE_BETWEEN_OPERATOR_DESCRIPTOR;
