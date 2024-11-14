import { type PropType } from 'vue';
import template from './sw-cms-section-actions.html.twig';
import './sw-cms-section-actions.scss';

/**
 * @package buyers-experience
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopware.Component.wrapComponentConfig({
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        section: {
            type: Object as PropType<EntitySchema.Entity<'cms_section'>>,
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

    data() {
        return {
            /* @deprecated: tag:v6.7.0 - Will be removed use cmsPageStateStore instead. */
            cmsPageState: Shopware.Store.get('cmsPage'),
        };
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

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.cmsPageState.selectedSection) {
                this.cmsPageStateStore.setSection(this.section);
            }
        },

        selectSection() {
            if (this.disabled) {
                return;
            }

            this.cmsPageStateStore.setSection(this.section);

            if (this.isCompatEnabled('INSTANCE_EVENT_EMITTER')) {
                this.$parent?.$parent?.$emit('page-config-open', 'itemConfig');
            } else {
                (this.swCmsSectionEmitPageConfigOpen as (arg: string) => void)?.('itemConfig');
            }
        },
    },
});
