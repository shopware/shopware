import template from './sw-cms-inherit-wrapper.html.twig';
import './sw-cms-inherit-wrapper.scss';
import type { CmsSlotConfig, RuntimeSlot } from '../../service/cms.service';

const { get, set, unset, has} = Shopware.Utils.object;
const { isEmpty, isUndefined } = Shopware.Utils.types;

/**
 * @private
 * @sw-package discovery
 *
 * @prop {Object} element - The CMS element object containing configuration and translation data.
 * @prop {Object} [contentEntity=null] - The content entity object that may contain slot configuration overrides,
 *                                       usually a product, category or landing page.
 * @prop {String} field - The specific configuration field within the element to manage inheritance for.
 * @prop {String} [fieldPath='value'] - The path within the configuration field to bind to, defaults to 'value'.
 * @prop {String} [label] - An optional label for the input field. Prefer this over the child component's label.
 */
export default Shopware.Component.wrapComponentConfig({
    template,
    emits: [
        'update:value',
        'inheritance:restore',
        'inheritance:remove',
    ],
    props: {
        element: {
            type: Object as PropType<RuntimeSlot & {
                translated: {
                    config?: CmsSlotConfig;
                }
            }>,
            required: true,
        },
        contentEntity: {
            type: Object as PropType<{
                slotConfig?: {
                    [slotId: string]: CmsSlotConfig;
                };
            }>,
            required: false,
            default: null,
        },
        field: {
            type: String,
            required: true,
        },
        fieldPath: {
            type: String,
            default() {
                return 'value';
            },
        },
        label: {
            type: String,
            required: false,
        },
    },
    computed: {
        currentValue: {
            get() {
                return get(this.runtimeConfig, this.fullPath);
            },
            set(value: string) {
                set(this.runtimeConfig, this.fullPath, value);

                this.$emit('update:value', value);
            },
        },
        baseConfig() {
            return this.element.translated?.config;
        },
        childConfig() {
            return this.contentEntity?.slotConfig?.[this.element.id];
        },
        runtimeConfig() {
            return this.element.config;
        },
        supportsInheritance() {
            return !!this.contentEntity;
        },
        canInheritField() {
            return has(this.baseConfig, this.fullPath);
        },
        /**
         * Fields are inherited if the layout is used on a content page (product, category, landing page)
         * and the field is not overridden in the <entity>.slot_config
         */
        isInherited() {
            return this.contentEntity && isUndefined(get(this.childConfig, this.fullPath));
        },
        fullPath() {
            return this.field.concat('.', this.fieldPath);
        },
    },
    methods: {
        onInheritanceRestore() {
            set(this.runtimeConfig, this.fullPath, get(this.baseConfig, this.fullPath, null));
            unset(this.childConfig, this.field);

            if (isEmpty(this.childConfig)) {
                unset(this.contentEntity, 'slotConfig');
            }

            this.$emit('inheritance:restore');
        },
        onInheritanceRemove() {
            if (!this.contentEntity) {
                return;
            }

            if (!this.contentEntity.slotConfig) {
                this.contentEntity.slotConfig = {};
            }

            if (!this.contentEntity.slotConfig[this.element.id]) {
                this.contentEntity.slotConfig[this.element.id] = {};
            }

            set(
                this.childConfig!,
                this.fullPath,
                get(this.baseConfig, this.fullPath, {
                    value: null,
                }),
            );

            this.$emit('inheritance:remove');
        },
    },
})
