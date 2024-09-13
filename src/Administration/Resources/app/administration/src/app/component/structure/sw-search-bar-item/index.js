import template from './sw-search-bar-item.html.twig';
import './sw-search-bar-item.scss';

const { Component, Application } = Shopware;
/**
 * @package admin
 *
 * @private
 * @description
 * Renders the search result items based on the item type.
 * @status ready
 * @example-type code-only
 * @component-example
 * <sw-search-bar-item :item="{ type: 'customer', entity: [{ name: 'customer name', id: 'uuid' }]}">
 * </sw-search-bar-item>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-search-bar-item', {
    template,

    compatConfig: Shopware.compatConfig,

    inject: {
        searchTypeService: 'searchTypeService',
        feature: 'feature',
        recentlySearchService: 'recentlySearchService',
        searchBarOnMouseOver: {
            from: 'searchBarOnMouseOver',
            default: null,
        },
        searchBarRegisterActiveItemIndexSelectHandler: {
            from: 'searchBarRegisterActiveItemIndexSelectHandler',
            default: null,
        },
        searchBarUnregisterActiveItemIndexSelectHandler: {
            from: 'searchBarUnregisterActiveItemIndexSelectHandler',
            default: null,
        },
        searchBarRegisterKeyupEnterHandler: {
            from: 'searchBarRegisterKeyupEnterHandler',
            default: null,
        },
        searchBarUnregisterKeyupEnterHandler: {
            from: 'searchBarUnregisterKeyupEnterHandler',
            default: null,
        },
    },

    props: {
        item: {
            type: Object,
            required: false,
            default: () => ({}),
        },
        type: {
            required: true,
            type: String,
        },
        index: {
            type: Number,
            required: true,
        },
        column: {
            type: Number,
            required: true,
        },
        searchTerm: {
            type: String,
            required: false,
            default: null,
        },
        entityIconColor: {
            type: String,
            required: true,
        },
        entityIconName: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            isActive: false,
        };
    },

    computed: {
        searchTypes() {
            return this.searchTypeService.getTypes();
        },

        moduleManifest() {
            const moduleFactory = Application.getContainer('factory').module;

            const module = moduleFactory.getModuleByEntityName(this.type) ?? {};

            return module.manifest;
        },

        detailRoute() {
            return this.moduleManifest?.routes?.detail?.name;
        },

        displayValue() {
            if (!this.moduleManifest.hasOwnProperty('entityDisplayProperty')) {
                return this.item.hasOwnProperty('name') ? this.item.name : this.item.id;
            }

            if (!this.item.hasOwnProperty(this.moduleManifest.entityDisplayProperty)) {
                return this.item.hasOwnProperty('name') ? this.item.name : this.item.id;
            }

            return this.item[this.moduleManifest.entityDisplayProperty];
        },

        componentClasses() {
            return [
                {
                    'is--active': this.isActive,
                },
            ];
        },

        moduleName() {
            const { action, label, entity, title } = this.item;

            if (title && !action) {
                return this.$tc(`${title}`, 2);
            }

            return action ? this.$tc(
                'global.sw-search-bar-item.addNewEntity',
                0,
                { entity: label?.toLowerCase() ?? this.$tc(`global.entities.${entity}`).toLowerCase() },
            ) : label;
        },

        routeName() {
            return typeof this.item.route === 'object' ? this.item.route : { name: this.item.route };
        },

        iconName() {
            return ['module', 'frequently_used'].includes(this.type) && this.item?.icon
                ? this.item.icon
                : this.entityIconName;
        },

        iconColor() {
            return ['module', 'frequently_used'].includes(this.type) && this.item?.color
                ? this.item.color
                : this.entityIconColor;
        },

        shortcut() {
            const { action, name } = this.item;

            if (!this.$te(`global.sw-search-bar-item.shortcuts.${name}`)) {
                return false;
            }

            return this.$tc(
                `global.sw-search-bar-item.shortcuts.${name}`,
                action ? 2 : 1,
            );
        },

        productDisplayName() {
            const name = this.item.translated?.name ?? this.item.name;
            const options = [];

            if (this.item?.variation?.length > 0) {
                this.item.variation.forEach((variation) => {
                    options.push(`${variation.group}: ${variation.option}`);
                });

                return `${name} (${options.join(' | ')})`;
            }

            return name;
        },

        currentUser() {
            return Shopware.State.get('session').currentUser;
        },

        mediaNameFilter() {
            return Shopware.Filter.getByName('mediaName');
        },
    },

    created() {
        this.createdComponent();
    },

    unmounted() {
        this.destroyedComponent();
    },

    methods: {
        createdComponent() {
            this.registerEvents();

            if (this.index === 0 && this.column === 0) {
                this.isActive = true;
            }
        },

        destroyedComponent() {
            this.removeEvents();
        },

        registerEvents() {
            if (this.isCompatEnabled('INSTANCE_EVENT_EMITTER')) {
                let parent = this.$parent;

                parent = this.$parent.$parent;

                parent.$on('active-item-index-select', this.checkActiveState);
                parent.$on('keyup-enter', this.onEnter);
            } else {
                this.searchBarRegisterActiveItemIndexSelectHandler(this.checkActiveState);
                this.searchBarRegisterKeyupEnterHandler(this.onEnter);
            }
        },

        removeEvents() {
            if (this.isCompatEnabled('INSTANCE_EVENT_EMITTER')) {
                let parent = this.$parent;

                parent = this.$parent.$parent;

                parent.$off('active-item-index-select', this.checkActiveState);
                parent.$off('keyup-enter', this.onEnter);
            } else {
                this.searchBarUnregisterActiveItemIndexSelectHandler(this.checkActiveState);
                this.searchBarUnregisterKeyupEnterHandler(this.onEnter);
            }
        },

        checkActiveState({ index, column }) {
            if (index === this.index && column === this.column) {
                this.isActive = true;
                return;
            }

            if (this.isActive) {
                this.isActive = false;
            }
        },

        onEnter(index, column) {
            if (index !== this.index || column !== this.column) {
                return;
            }

            const routerLink = this.$refs.routerLink;
            this.$router.push(routerLink.to);
        },

        onMouseEnter(originalDomEvent) {
            if (this.isCompatEnabled('INSTANCE_EVENT_EMITTER')) {
                let parent = this.$parent;

                parent = this.$parent.$parent;

                parent.$emit('mouse-over', {
                    originalDomEvent,
                    index: this.index,
                    column: this.column,
                });
            } else {
                this.searchBarOnMouseOver({
                    originalDomEvent,
                    index: this.index,
                    column: this.column,
                });
            }

            this.isActive = true;
        },

        onClickSearchResult(entity, id, payload = {}) {
            this.recentlySearchService.add(this.currentUser.id, entity, id, payload);
        },
    },
});
