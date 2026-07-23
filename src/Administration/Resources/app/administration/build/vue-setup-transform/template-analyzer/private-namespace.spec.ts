/**
 * @sw-package framework
 */

import { createOverridePrivateNamespace } from './index';

describe('build/vue-setup-transform/template-analyzer private namespace', () => {
    it('is deterministic for the same file and component', () => {
        expect(createOverridePrivateNamespace('src/a/sw-thing.override.vue', 'sw-thing')).toBe(
            createOverridePrivateNamespace('src/a/sw-thing.override.vue', 'sw-thing'),
        );
    });

    it('differs for the same basename in different directories', () => {
        expect(createOverridePrivateNamespace('src/a/sw-thing.override.vue', 'sw-thing')).not.toBe(
            createOverridePrivateNamespace('src/b/sw-thing.override.vue', 'sw-thing'),
        );
    });
});
