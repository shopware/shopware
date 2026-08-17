/**
 * @sw-package framework
 * @private
 */

import { type ComposableDescriptor, methodMembers } from '../types';

const VALIDATION_DESCRIPTOR: ComposableDescriptor = {
    id: 'validation',
    mixinNames: ['validation'],
    import: { source: 'src/app/composables/use-validation', name: 'useValidation' },
    members: {
        validationService: { kind: 'value' },
        ...methodMembers([
            'validate',
            'validateRule',
        ]),
    },
    internallyReferencedMembers: ['validateRule'],
    // The mixin's computed read the host's current value under whichever of `currentValue`, `value`
    // or `selections` existed, a name the composable cannot know; its callers pass the value to
    // `validate()` instead.
    unmappedMembers: ['isValid'],
    propArgs: ['validation'],
    providedProps: [
        {
            name: 'validation',
            definition: '{\ntype: [String, Array, Object, Boolean],\nrequired: false,\ndefault: null,\n}',
        },
    ],
};

export default VALIDATION_DESCRIPTOR;
