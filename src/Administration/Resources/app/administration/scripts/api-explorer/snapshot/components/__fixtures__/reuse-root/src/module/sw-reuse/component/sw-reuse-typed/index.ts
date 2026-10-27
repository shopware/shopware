import { DEFAULT_UNIT } from '../../../../core/units';
import template from './sw-reuse-typed.html.twig';

export default Shopware.Component.wrapComponentConfig({
    template,

    methods: {
        unit() {
            return DEFAULT_UNIT;
        },

        layout() {
            return FIXTURE_LAYOUT;
        },
    },
});
