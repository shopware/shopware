import { type PropType } from 'vue';
import template from './sw-cms-slot.html.twig';
import './sw-cms-slot.scss';
import { type CmsElementConfig } from '../../service/cms.service';

const { deepCopyObject } = Shopware.Utils.object;

/**
 * @private since v6.5.0
 * @package buyers-experience
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    compatConfig: Shopware.compatConfig,

    inject: [
        'cmsService',
        'cmsElementFavorites',
    ],

    props: {
        element: {
            type: Object as PropType<EntitySchema.Entity<'cms_slot'>>,
            required: true,
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
            elementNotFound: false,
        };
    },

    computed: {
        slotElementId() {
            return this.element.id;
        },

        cmsServiceState() {
            return this.cmsService.getCmsServiceState();
        },

        elementConfig() {
            return this.cmsServiceState.elementRegistry[this.element.type];
        },

        cmsElements() {
            const currentPageType = Shopware.Store.get('cmsPageState').currentPageType;

            if (!currentPageType) {
                return {};
            }

            const elements = Object.entries(this.cmsService.getCmsElementRegistry())
                .filter(([name]) => this.cmsService.isElementAllowedInPageType(name, currentPageType));

            return Object.fromEntries(elements);
        },

        groupedCmsElements() {
            const result = [];
            const elements = Object.values(this.cmsElements).sort((a, b) => {
                if (!a || !b) {
                    return 0;
                }

                return a.name.localeCompare(b.name);
            });
            const favorites = elements.filter(element => element && this.cmsElementFavorites.isFavorite(element.name));
            const nonFavorites = elements.filter(element => !element || !this.cmsElementFavorites.isFavorite(element.name));

            if (favorites.length) {
                result.push({
                    title: 'sw-cms.elements.general.switch.groups.favorites',
                    items: favorites,
                });
            }

            result.push({
                title: 'sw-cms.elements.general.switch.groups.all',
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
            if (this.elementConfig?.defaultConfig && !this.element?.locked) {
                return null;
            }

            return 'is--disabled';
        },

        tooltipDisabled() {
            if (this.elementConfig?.disabledConfigInfoTextKey) {
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

        modalVariant() {
            return this.element.type === 'html' ? 'full' : 'large';
        },
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            // Show a "Not found" error after 10 seconds, when no element has been found
            setTimeout(() => {
                if (!this.elementConfig) {
                    this.elementNotFound = true;
                }
            }, 10000);
        },

        onSettingsButtonClick() {
            if (!this.elementConfig?.defaultConfig || this.element?.locked) {
                return;
            }
            this.showElementSettings = true;
        },

        onCloseSettingsModal() {
            const childComponent = this.$refs.elementComponentRef as { handleUpdateContent: () => void };

            if (childComponent?.handleUpdateContent) {
                childComponent.handleUpdateContent();
            }

            this.showElementSettings = false;
        },

        onElementButtonClick() {
            this.showElementSelection = true;
        },

        onCloseElementModal() {
            this.showElementSelection = false;
        },

        onSelectElement(element: CmsElementConfig) {
            this.element.data = deepCopyObject(element?.defaultData || {});
            this.element.config = deepCopyObject(element?.defaultConfig || {});
            this.element.type = element.name;
            this.element.locked = false;

            if (this.element.translated?.config) {
                this.element.translated.config = {};
            }

            this.showElementSelection = false;
        },

        onToggleElementFavorite(elementName: string) {
            this.cmsElementFavorites.update(!this.cmsElementFavorites.isFavorite(elementName), elementName);
        },

        elementInElementGroup(element: CmsElementConfig, elementGroup: string) {
            if (elementGroup === 'favorite') {
                return this.cmsElementFavorites.isFavorite(element.name);
            }

            return true;
        },
    },
});
