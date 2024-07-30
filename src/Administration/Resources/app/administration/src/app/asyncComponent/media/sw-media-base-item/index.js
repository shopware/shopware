import template from './sw-media-base-item.html.twig';
import './sw-media-base-item.scss';

/**
 * @status ready
 * @description The <u>sw-media-base-item</u> component is the base for items in the media manager.
 * @package content
 * @example-type code-only
 * @component-example
 * <sw-media-base-item
 *     :item="myItem"
 *     :is-list="true">
 * </sw-media-base-item>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['systemConfigApiService'],

    props: {
        item: {
            type: Object,
            required: true,
        },

        isList: {
            type: Boolean,
            required: false,
            default: false,
        },

        showSelectionIndicator: {
            required: false,
            type: Boolean,
            default: false,
        },

        showContextMenuButton: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },

        selected: {
            type: Boolean,
            required: false,
            default: false,
        },

        editable: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },

        allowMultiSelect: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },

        truncateRight: {
            type: Boolean,
            required: false,
            default: false,
        },

        allowEdit: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },

        allowDelete: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
    },

    data() {
        return {
            isInlineEdit: false,
            defaultArReady: false,
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
        mediaItemClasses() {
            return {
                'is--list': this.isList,
                'is--selected': this.selected || this.isInlineEdit,
            };
        },

        mediaNameContainerClasses() {
            return {
                'is--truncate-right': this.truncateRight,
            };
        },

        listSelected() {
            return this.selected && this.showSelectionIndicator;
        },

        selectionIndicatorClasses() {
            return {
                'selected-indicator--visible': this.showSelectionIndicator,
                'selected-indicator--list': this.isList,
                'selected-indicator--checked': this.listSelected,
                'selected-indicator--is-allowed': this.allowMultiSelect,
            };
        },

        isLoading() {
            return this.item.isLoading;
        },

        /**
         * @experimental stableVersion:v6.7.0 feature:SPATIAL_BASES
         */
        isSpatial() {
            // we need to check the media url since media.fileExtension is set directly after upload
            return this.item.fileExtension === 'glb' || !!this.item?.url?.endsWith('.glb');
        },
    },

    methods: {
        createdComponent() {
            this.systemConfigApiService.getValues('core.media')
                .then((values) => {
                    this.defaultArReady = values['core.media.defaultEnableAugmentedReality'];
                });
        },

        handleItemClick(originalDomEvent) {
            if (this.isSelectionIndicatorClicked(originalDomEvent.composedPath())) {
                return;
            }
            this.$emit('media-item-click', {
                originalDomEvent,
                item: this.item,
            });
        },

        isSelectionIndicatorClicked(path) {
            return path.some((parent) => {
                return parent.classList && (
                    parent.classList.contains('sw-media-base-item__selected-indicator') ||
                    parent.classList.contains('sw-context-button')
                );
            });
        },

        onClickedItem(originalDomEvent) {
            if (!this.listSelected || !this.allowMultiSelect) {
                this.selectItem(originalDomEvent);
                return;
            }
            this.removeFromSelection(originalDomEvent);
        },

        selectItem(originalDomEvent) {
            this.$emit('media-item-selection-add', {
                originalDomEvent,
                item: this.item,
            });
        },

        removeFromSelection(originalDomEvent) {
            this.$emit('media-item-selection-remove', {
                originalDomEvent,
                item: this.item,
            });
        },

        startInlineEdit() {
            if (this.editable && this.allowEdit) {
                this.isInlineEdit = true;
            }
        },

        endInlineEdit() {
            this.isInlineEdit = false;
        },
    },
};
