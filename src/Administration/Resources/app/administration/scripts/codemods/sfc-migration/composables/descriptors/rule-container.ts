/**
 * @sw-package framework
 * @private
 */

import { type ComposableDescriptor, methodMembers, refMembers } from '../types';

const RULE_CONTAINER_DESCRIPTOR: ComposableDescriptor = {
    id: 'rule-container',
    mixinNames: ['ruleContainer'],
    import: { source: 'src/app/composables/use-rule-container', name: 'useRuleContainer' },
    members: {
        ...refMembers([
            'conditionDataProviderService',
            'childAssociationField',
            'containerRowClass',
            'nextPosition',
        ]),
        ...methodMembers([
            'createCondition',
            'insertNodeIntoTree',
            'removeNodeFromTree',
        ]),
    },
    // nextPosition counts the children under the provided association field, and the watcher reads
    // it back before asking for a placeholder.
    internallyReferencedMembers: [
        'childAssociationField',
        'nextPosition',
    ],
    propArgs: [
        'condition',
        'level',
        'disabled',
    ],
    callbackArgs: [
        { name: 'onAddPlaceholder', kind: 'callback' },
    ],
    // `parentCondition` is the fourth prop the mixin declared, which its own logic never read.
    providedProps: [
        { name: 'condition', definition: '{\ntype: Object,\nrequired: true,\n}' },
        { name: 'parentCondition', definition: '{\ntype: Object,\nrequired: false,\ndefault: null,\n}' },
        { name: 'level', definition: '{\ntype: Number,\nrequired: true,\n}' },
        { name: 'disabled', definition: '{\ntype: Boolean,\nrequired: false,\ndefault: false,\n}' },
    ],
};

export default RULE_CONTAINER_DESCRIPTOR;
