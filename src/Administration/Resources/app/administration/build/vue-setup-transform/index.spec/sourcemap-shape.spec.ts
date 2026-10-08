/**
 * @sw-package framework
 */

import { transformOrFail } from './helpers';
import { parseSourceMap } from './sourcemap-helpers';

describe('build/vue-setup-transform sourcemap shape', () => {
    it('returns a content-bearing sourcemap for transformed setup SFCs', () => {
        const source = `<script setup>
const count = 1;

swDefinePublic({
    count,
});
</script>`;

        const result = transformOrFail(source, 'content-bearing.vue');
        const map = parseSourceMap(result);

        expect(map.sources).toContain('content-bearing.vue');
        expect(map.sourcesContent).toContain(source);
        expect(map.mappings).not.toBe('');
    });

    it('preserves the full input filename instead of collapsing sources to the basename', () => {
        const source = `<script setup>
const count = 1;

swDefinePublic({
    count,
});
</script>`;
        const firstResult = transformOrFail(source, '/extension-a/src/component.vue');
        const secondResult = transformOrFail(source, '/extension-b/src/component.vue');
        const firstMap = parseSourceMap(firstResult);
        const secondMap = parseSourceMap(secondResult);

        expect(firstMap.sources).toContain('/extension-a/src/component.vue');
        expect(firstMap.sources).not.toContain('component.vue');
        expect(secondMap.sources).toContain('/extension-b/src/component.vue');
        expect(secondMap.sources).not.toContain('component.vue');
    });
});
