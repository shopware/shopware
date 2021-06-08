import template from './sw-flow-trigger.html.twig';
import './sw-follow-trigger.scss';

const { Component } = Shopware;
const { mapPropertyErrors, mapState } = Component.getComponentHelper();

Component.register('sw-flow-trigger', {
    template,

    inject: ['repositoryFactory', 'businessEventService'],

    props: {
        collapsible: {
            type: Boolean,
            required: false,
            default: true,
        },
        overlay: {
            type: Boolean,
            required: false,
            default: true,
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
        eventName: {
            type: String,
            required: true,
            default() {
                return '';
            },
        },
    },

    data() {
        return {
            events: [],
            isExpanded: false,
            searchTerm: '',
            searchResult: [],
        };
    },

    computed: {
        swFlowTriggerClasses() {
            return { overlay: this.overlay };
        },

        formatEventName() {
            if (!this.eventName) {
                return this.eventName;
            }

            return this.getEventName(this.eventName);
        },

        showTreeView() {
            return this.eventTree.length >= 0
                && (this.searchTerm.length <= 0 || this.searchTerm === this.formatEventName);
        },

        eventTree() {
            return this.getEventsTree(this.events);
        },

        ...mapState('swFlowState', ['flow']),
        ...mapPropertyErrors('flow', ['eventName']),
    },

    watch: {
        eventName: {
            immediate: true,
            handler(value) {
                if (!value) {
                    return;
                }

                this.$route.params.eventName = value;
                this.searchTerm = this.getEventName(value);
            },
        },

        searchTerm(value) {
            if (!value || value === this.formatEventName) {
                return;
            }

            const keyWords = value.split(/[\W_]+/ig);

            this.searchResult = this.events.filter(event => {
                return keyWords.every(key => event.name.includes(key.toLowerCase()));
            });
        },
    },

    created() {
        this.createdComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    methods: {
        createdComponent() {
            document.addEventListener('click', this.closeOnClickOutside);
            document.addEventListener('keyup', this.closeOnClickOutside);

            this.openInitialTree();
        },

        destroyedComponent() {
            document.removeEventListener('click', this.closeOnClickOutside);
            document.removeEventListener('keyup', this.closeOnClickOutside);
        },

        closeOnClickOutside(event) {
            if (event.type === 'keyup' && event.key.toLowerCase() !== 'tab') {
                return;
            }

            const target = event.target;

            if (target.closest('.sw-tree-item .is--no-children .sw-tree-item__content')) {
                this.isExpanded = false;
                return;
            }

            if (target.closest('.sw-flow-trigger__search-result')) {
                this.isExpanded = false;
                return;
            }

            if (target.closest('.sw-flow-trigger') === null) {
                if (target.closest('svg')) {
                    return;
                }

                this.isExpanded = false;

                if (this.searchTerm !== this.formatEventName) {
                    this.searchTerm = this.formatEventName;
                }
            }
        },

        onFocusTrigger() {
            this.isExpanded = true;
        },

        changeTrigger(item) {
            if (item?.childCount > 0) {
                return;
            }

            const { id } = item.data;

            this.$emit('option-select', id);
        },

        openInitialTree() {
            this.getBusinessEvents().then(events => {
                this.events = events;
            });
        },

        getBusinessEvents() {
            return this.businessEventService.getBusinessEvents();
        },

        getLastEventName({ name = null, parentId = null, id }) {
            if (!name) {
                const [eventName] = parentId ? id.split('.').reverse()
                    : [id];

                return eventName.replace(/-|_|\./g, ' ');
            }

            const [childEventName] = name.split('.').reverse();

            return childEventName.replace(/-|_|\./g, ' ');
        },

        getEventsTree(events) {
            const mappedObj = {};

            events.forEach(event => {
                const parts = event.name.split('.');
                if (parts.length === 0) {
                    return;
                }

                const recursive = (currentIndex, recursiveParts, currentObj) => {
                    const currentPart = recursiveParts[currentIndex];
                    const nextIndex = currentIndex + 1;
                    const nextPart = recursiveParts[nextIndex];

                    currentObj[currentPart] = currentObj[currentPart] || {
                        id: currentPart,
                        parentId: null,
                        children: {},
                    };

                    currentObj[currentPart].name = nextPart ? null : event.name;

                    if (!nextPart) {
                        return;
                    }

                    currentObj[currentPart].children[nextPart] = currentObj[currentPart].children[nextPart] || {
                        id: `${currentObj[currentPart].id}.${nextPart}`,
                        parentId: currentObj[currentPart].id,
                        children: {},
                    };

                    recursive(nextIndex, recursiveParts, currentObj[currentPart].children);
                };

                recursive(0, parts, mappedObj);
            });

            const treeToArray = (nodes, output = []) => {
                nodes.forEach(node => {
                    const children = node.children ? Object.values(node.children) : [];

                    output.push({
                        id: node.id,
                        name: this.getLastEventName(node),
                        childCount: children.length,
                        parentId: node.parentId,
                    });
                    if (children.length > 0) {
                        output = treeToArray(children, output);
                    }
                });
                return output;
            };

            return treeToArray(Object.values(mappedObj));
        },

        getBreadcrumb(item) {
            const keyWords = item.name.split('.');

            return keyWords.map(key => {
                return key.charAt(0).toUpperCase() + key.slice(1);
            }).join(' / ').replace(/-|_|\./g, ' ');
        },

        onClickSearchItem(item) {
            this.$emit('option-select', item.name);
            this.searchTerm = this.formatEventName;
            this.searchResult = [];
        },

        getEventName(eventName) {
            if (!eventName) {
                return eventName;
            }

            return eventName.replace(/\./g, ' / ').replace(/-|_|\./g, ' ');
        },
    },
});
