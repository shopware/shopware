/**
 * @sw-package framework
 */

/**
 * Covers the end-to-end override lowering with no template involved: how an override `<script setup>`
 * becomes a hidden component that registers the setup override, the `swDefineOverride` return payload,
 * and how imports and type declarations are preserved in the generated component.
 *
 * Template-driven forwarding lives in override-template.spec.ts; the destructuring-pattern edge cases
 * in override-template-patterns.spec.ts.
 */

import { stripIndent, transformOrFail } from './helpers';

describe('build/vue-setup-transform override transforms', () => {
    it('transforms override Shopware setup blocks into hidden override components', () => {
        const source = stripIndent`
            <script setup>
            import { computed } from 'vue';

            const previousState = useSwPreviousState();
            const props = useSwProps();
            const context = useSwContext();

            const doubled = computed(() => previousState.count.value * 2);

            swDefineOverride({
                doubled,
            });
            </script>
        `;

        const expected = stripIndent`
            <script>
            import { computed } from 'vue';

            export default {
                setup() {
                    Shopware.Component.overrideComponentSetup()('sw-my-component', (__swSetupPreviousState, __swSetupProps, __swSetupContext) => {
                        const useSwPreviousState = () => __swSetupPreviousState;
                        const useSwProps = () => __swSetupProps;
                        const useSwContext = () => __swSetupContext;

                        const previousState = useSwPreviousState();
                        const props = useSwProps();
                        const context = useSwContext();

                        const doubled = computed(() => previousState.count.value * 2);

                        return {
                            doubled,
                        };
                    });

                    return () => null;
                },
            };
            </script>
        `;

        expect(transformOrFail(source, 'sw-my-component.override.vue').code).toBe(expected);
    });

    it('transforms sw-override blocks in .override.vue files', () => {
        const source = stripIndent`
            <script setup>
            const count = 1;
            swDefineOverride({ count });
            </script>
        `;

        const result = transformOrFail(source, 'component-name.override.vue');

        expect(result.mode).toBe('override');
        expect(result.filename).toBe('component-name.override.vue');
        expect(result.code).toContain("Shopware.Component.overrideComponentSetup()('component-name'");
    });

    it('keeps imports out of returned override state', () => {
        const source = stripIndent`
            <script setup>
            import { computed } from 'vue';

            const doubled = computed(() => 2);

            swDefineOverride({
                doubled,
            });
            </script>
        `;

        const result = transformOrFail(source, 'component.override.vue').code;

        expect(result).toContain('return {\n                doubled,\n            };');
        expect(result).not.toContain('computed,');
    });

    it('keeps local type declarations available for override callback code', () => {
        const source = stripIndent`
            <script setup lang="ts">
            type Props = {
                label?: string;
            };

            const props = useSwProps<Props>();
            const label = props.label ?? 'fallback';

            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'typed.override.vue').code;

        expect(result.indexOf('type Props')).toBeLessThan(result.indexOf('export default'));
        expect(result).toContain('const props = useSwProps<Props>();');
        expect(result.match(/type Props/g)).toHaveLength(1);
    });

    it('uses swDefineOverride() as the explicit override payload and keeps unused local state private', () => {
        const source = stripIndent`
            <script setup>
            import { computed, ref } from 'vue';

            const previousState = useSwPreviousState();
            const body = computed(() => previousState.body.value);
            const localInfo = ref('only for script logic');
            const localHeadline = computed(() => localInfo.value);
            const localFooter = computed(() => localInfo.value);

            swDefineOverride({
                body,
                localHeadline,
                localFooter,
            });
            </script>
        `;

        const result = transformOrFail(source, 'explicit-payload.override.vue').code;

        expect(result).toContain(
            'return {\n                body,\n                localHeadline,\n                localFooter,\n            };',
        );
        expect(result).not.toContain('__swOverride');
        expect(result).not.toContain('localInfo,');
    });
});
