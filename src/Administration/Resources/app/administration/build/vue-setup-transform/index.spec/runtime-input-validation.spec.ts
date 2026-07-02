/**
 * @sw-package framework
 */

import { stripIndent, transformShopwareSetupSfc } from './helpers';

describe('build/vue-setup-transform runtime input validation', () => {
    it('rejects useSwProps() in base mode', () => {
        const source = stripIndent`
            <script setup>
            const props = useSwProps();
            const count = props.initialCount ?? 0;

            swDefinePublic({
                count,
            });
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'base-use-sw-props.vue')).toThrow(
            "useSwProps() is only supported in override Shopware setup blocks. Base components must use Vue's defineProps() macro instead.",
        );
    });

    it('rejects useSwPreviousState() in base mode', () => {
        const source = stripIndent`
            <script setup>
            const previousState = useSwPreviousState();
            const count = 1;

            swDefinePublic({
                count,
            });
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'base-previous-state.vue')).toThrow(
            'useSwPreviousState() is only supported in override Shopware setup blocks.',
        );
    });
});
