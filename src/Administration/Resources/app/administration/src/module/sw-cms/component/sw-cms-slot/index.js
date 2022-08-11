import template from './sw-cms-slot.html.twig';
import './sw-cms-slot.scss';

const { Component } = Shopware;

Component.register('sw-cms-slot', {
    template,

    inject: [
        'cmsService',
        'cmsElementFavorites',
    ],

    props: {
        element: {
            type: Object,
            required: true,
            default() {
                return {};
            },
        },

        active: {
            type: Boolean,
            required: false,
            default: false,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            showElementSettings: false,
            showElementSelection: false,
        };
    },

    computed: {
        slotElementId() {
            return this.element.id;
        },

        elementConfig() {
            return this.cmsService.getCmsElementConfigByName(this.element.type);
        },

        cmsElements() {
            return this.cmsService.getCmsElementRegistry();
        },

        groupedCmsElements() {
            const result = [];
            const elements = Object.values(this.cmsElements).sort((a, b) => a.name.localeCompare(b.name));
            const favorites = elements.filter(e => this.cmsElementFavorites.isFavorite(e.name));
            const nonFavorites = elements.filter(e => !this.cmsElementFavorites.isFavorite(e.name));

            if (favorites.length) {
                result.push({
                    title: this.$t('sw-cms.elements.general.switch.groups.favorites'),
                    items: favorites,
                });
            }

            result.push({
                title: this.$t('sw-cms.elements.general.switch.groups.all'),
                items: nonFavorites,
            });

            return result;
        },

        componentClasses() {
            const componentClass = `sw-cms-slot-${this.element.slot}`;

            return {
                'is--disabled': this.disabled,
                [componentClass]: !!this.element.slot,
            };
        },

        cmsSlotSettingsClasses() {
            if (this.elementConfig.defaultConfig && !this.element.locked) {
                return null;
            }

            return 'is--disabled';
        },

        tooltipDisabled() {
            if (this.elementConfig.disabledConfigInfoTextKey) {
                return {
                    message: this.$tc(this.elementConfig.disabledConfigInfoTextKey),
                    disabled: !!this.elementConfig.defaultConfig && !this.element.locked,
                };
            }

            return {
                message: this.$tc('sw-cms.elements.general.config.tab.settings'),
                disabled: true,
            };
        },
    },

    methods: {
        onSettingsButtonClick() {
            if (!this.elementConfig.defaultConfig || this.element.locked) {
                return;
            }

            this.showElementSettings = true;
        },

        onCloseSettingsModal() {
            this.showElementSettings = false;
        },

        onElementButtonClick() {
            this.showElementSelection = true;
        },

        onCloseElementModal() {
            this.showElementSelection = false;
        },

        onSelectElement(elementType) {
            this.element.data = {};
            this.element.config = {};
            this.element.type = elementType;
            this.element.locked = false;
            this.showElementSelection = false;
        },

        onToggleElementFavorite(elementName) {
            this.cmsElementFavorites.update(!this.cmsElementFavorites.isFavorite(elementName), elementName);
        },

        elementInElementGroup(element, elementGroup) {
            if (elementGroup === 'favorite') {
                return this.cmsElementFavorites.isFavorite(element.name);
            }

            return true;
        },
    },
});
