/**
 * @sw-package framework
 *
 * Covers what the transform adds to an override `<sw-block extends>`. Rejections live in
 * `override-template-guards.spec.ts`.
 */

import { stripIndent, transformOrFail } from './helpers';

describe('build/vue-setup-transform override template', () => {
    it('does not add generated data scope to override sw-block extensions', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_headline">
                <h2>{{ headline }}</h2>
            </sw-block>
            </template>
            <script setup>
            const headline = 'Headline';

            swDefineOverride({
                headline,
            });
            </script>
        `;

        const result = transformOrFail(source, 'override-sw-block-data.override.vue').code;

        expect(result).toContain(
            `<sw-block sw-internal-component-name='override-sw-block-data' extends="sw_example_component_headline">`,
        );
        expect(result).not.toContain(':data="$dataScope"');
    });

    it('emits the extended block names for the ownership cross-check', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_headline">
                <h2>headline</h2>
            </sw-block>
            <sw-block extends="sw_example_component_body">
                <p>body</p>
            </sw-block>
            </template>
            <script setup>
            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'extended-names.override.vue');

        expect(result.extendedBlockNames).toEqual(['sw_example_component_headline', 'sw_example_component_body']);
        expect(result.ownedBlockNames).toEqual([]);
    });
});
