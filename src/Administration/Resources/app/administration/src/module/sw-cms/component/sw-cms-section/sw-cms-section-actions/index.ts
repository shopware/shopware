import { type PropType } from 'vue';
import template from './sw-cms-section-actions.html.twig';
import './sw-cms-section-actions.scss';

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        section: {
            type: Object as PropType<Entity<'cms_section'>>,
            required: true,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    inject: {
        feature: {
            from: 'feature',
            default: null,
        },

        swCmsSectionEmitPageConfigOpen: {
            from: 'swCmsSectionEmitPageConfigOpen',
            default: null,
        },
    },

    computed: {
        componentClasses() {
            return {
                'is--disabled': this.disabled,
            };
        },
        cmsPageStateStore() {
            return Shopware.Store.get('cmsPage');
        },
    },

    methods: {
        selectSection() {
            if (this.disabled) {
                return;
            }

            this.cmsPageStateStore.setSection(this.section);

            (this.swCmsSectionEmitPageConfigOpen as (arg: string) => void)?.('itemConfig');
        },
    },
});
