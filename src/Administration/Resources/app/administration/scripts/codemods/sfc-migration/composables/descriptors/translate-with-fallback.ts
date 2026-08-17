/**
 * @sw-package framework
 */

import { type ComposableDescriptor, methodMembers } from '../types';

const TRANSLATE_WITH_FALLBACK_DESCRIPTOR: ComposableDescriptor = {
    id: 'translate-with-fallback',
    mixinNames: ['translate-with-fallback'],
    import: {
        source: 'src/app/composables/use-translate-with-fallback',
        name: 'useTranslateWithFallback',
    },
    members: methodMembers([
        'tWithFallback',
    ]),
};

export { TRANSLATE_WITH_FALLBACK_DESCRIPTOR };
