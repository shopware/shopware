import template from './sw-cms-inherit-wrapper.html.twig';
import './sw-cms-inherit-wrapper.scss';
import type { CmsSlotConfig, RuntimeSlot } from '../../service/cms.service';

const { get, set, unset, has, cloneDeep } = Shopware.Utils.object;
const { isEmpty, isUndefined } = Shopware.Utils.types;

const EVENTS = {
    RESTORE: 'inheritance:restore',
    REMOVE: 'inheritance:remove',
};

const BASE_FIELD_FALLBACK = {
    value: null,
    source: 'static',
};

/**
 * @private
 * @sw-package discovery
 *
 * @description This component is used to provide inheritance support for CMS element configuration fields.
 *              The intended use is to wrap individual input fields of a CMS element configuration component,
 *              which means that a <sw-cms-inherit-wrapper /> is managing the state of a specific field of the
 *              elements cms_slot_translation.config or category_translation.slot_config.
 * @component-example
 * <sw-cms-inherit-wrapper
 *     :element="element"
 *     field="backgroundColor"
 *     :label="$tc('sw-cms.elements.image.labelBackgroundColor')"
 * >
 *     <template #default={ isInherited }>
 *         <mt-colorpicker
               v-model=element.config.backgroundColor.value
 *             :disabled="isInherited"
 *         />
 *     </template>
 * </sw-cms-inherit-wrapper>
 *
 * @prop {Object} element - The CMS element object containing configuration and translation data.
 * @prop {String} field - The specific configuration field within the element to manage inheritance for.
 * @prop {String} [fieldPath='value'] - The path within the configuration field to bind to, defaults to 'value'.
 * @prop {String} [label] - An optional label for the input field. Prefer this over the child component's label.
 */
export default Shopware.Component.wrapComponentConfig({
    template,
    inject: ['cmsService'],
    mixins: [
        Shopware.Mixin.getByName('cms-state'),
    ],
    emits: [
        EVENTS.RESTORE,
        EVENTS.REMOVE,
    ],
    props: {
        element: {
            type: Object as PropType<
                RuntimeSlot & {
                    translated: {
                        config?: CmsSlotConfig;
                    };
                }
            >,
            required: true,
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
    data() {
        return {
            showModal: false,
            originValue: null,
        } as {
            showModal: boolean;
            originValue: unknown;
        };
    },
    computed: {
        cmsElements() {
            return this.cmsService.getCmsElementRegistry();
        },
        baseConfig() {
            return this.element.getOrigin().translated?.config;
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
        /**
         * Fields are inherited if the layout is used on a content page (product, category, landing page)
         * and the field is not overridden in the <entity>.slot_config
         */
        isInherited() {
            return this.supportsInheritance && isUndefined(get(this.childConfig, this.field));
        },
        fullPath() {
            return this.field.concat('.', this.fieldPath);
        },
        fieldDefaultValue() {
            return get(this.cmsElements[this.element.type]?.defaultConfig, this.fullPath, BASE_FIELD_FALLBACK.value);
        },
    },
    methods: {
        async onInheritanceRestore() {
            this.showModal = false;

            set(this.runtimeConfig, this.field, cloneDeep(get(this.baseConfig, this.field, BASE_FIELD_FALLBACK)));

            /**
             * Run watchers before removing the slotConfig to ensure sw-cms-form-sync won't
             * override the reset.
             */
            await this.$nextTick();

            unset(this.childConfig, this.field);

            if (isEmpty(this.childConfig)) {
                set(this.contentEntity!, 'slotConfig', null);
            }

            this.$emit(EVENTS.RESTORE);
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

            const baseField = cloneDeep(get(this.baseConfig, this.field, BASE_FIELD_FALLBACK));

            if (!has(this.childConfig, this.field)) {
                set(this.childConfig!, this.field, baseField);
            }

            set(this.childConfig!, this.fullPath, get(baseField, this.fieldPath));
            set(this.runtimeConfig, this.fullPath, get(baseField, this.fieldPath));

            this.$emit(EVENTS.REMOVE);
        },
    },
});
