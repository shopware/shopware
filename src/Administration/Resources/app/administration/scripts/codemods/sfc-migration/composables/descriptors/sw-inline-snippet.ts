/**
 * @sw-package framework
 */

import { type ComposableDescriptor, methodMembers } from '../types';

const SW_INLINE_SNIPPET_DESCRIPTOR: ComposableDescriptor = {
    id: 'sw-inline-snippet',
    mixinNames: ['sw-inline-snippet'],
    import: { source: 'src/app/composables/use-inline-snippet', name: 'useInlineSnippet' },
    members: methodMembers([
        'getInlineSnippet',
    ]),
    unmappedMembers: [
        'swInlineSnippetLocale',
        'swInlineSnippetFallbackLocale',
    ],
};

export { SW_INLINE_SNIPPET_DESCRIPTOR };
