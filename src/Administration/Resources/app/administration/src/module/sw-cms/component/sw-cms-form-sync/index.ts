import type { PropType } from 'vue';
import type { RuntimeSlot } from '../../service/cms.service';

const { get, set, getObjectDiff } = Shopware.Utils.object;
const { isEmpty } = Shopware.Utils.types;

type FieldConfig = {
    value: unknown;
    [key: string]: unknown;
}

/**
 * Since the CMS layout editor is always editing the cms_page even if used on content pages,
 * this component is used to sync changes made to the cms_page back to the content entity.
 *
 * @prop {Object} element - The CMS element object containing configuration and translation data.
 *
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template: '<slot />',
    inject: ['cmsService'],
    mixins: [
        Shopware.Mixin.getByName('cms-state'),
    ],
    props: {
        element: {
            type: Object as PropType<RuntimeSlot>,
            required: true,
        },
    },
    computed: {
        cmsElements() {
            return this.cmsService.getCmsElementRegistry();
        },
    },
    created() {
        this.createFieldWatcher();
    },
    methods: {
        createFieldWatcher() {
            if (!this.contentEntity) {
                return;
            }

            const config = this.cmsElements[this.element.type]?.defaultConfig as Record<string, unknown>;

            if (!config) {
                return;
            }

            Object.keys(config).forEach(this.createWatcher.bind(this));
        },
        createWatcher(field: string) {
            this.$watch(() => this.element.config[field], this.fieldChangeHandler.bind(this, field), {
                deep: true,
            });
        },
        fieldChangeHandler(key: string, config: FieldConfig) {
            const path = `slotConfig.${this.element.id}.${key}`;

            if (isEmpty(getObjectDiff(config, get(this.contentEntity, path)))) {
                return;
            }

            set(this.contentEntity!, path, config);
        },
    },
});
