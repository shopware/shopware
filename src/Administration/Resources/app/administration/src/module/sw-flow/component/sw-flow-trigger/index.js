import template from './sw-flow-trigger.html.twig';
import './sw-follow-trigger.scss';

const { Component } = Shopware;

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
            displayTree: false,
        };
    },

    computed: {
        swFlowTriggerClasses() {
            return { overlay: this.overlay };
        },

        getEventName: {
            get() {
                if (!this.eventName) {
                    return this.eventName;
                }

                return this.eventName.replace(/\./g, ' / ').replace(/-|_|\./g, ' ');
            },

            set(newValue) {
                return newValue;
            },
        },
    },

    watch: {
        eventName(value) {
            if (!value) {
                return;
            }

            this.$route.params.eventName = value;
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

            if (this.eventName) {
                // open tree
                this.$route.params.eventName = this.eventName;
            }
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

            if (target.closest('.sw-tree-item__children')) {
                this.displayTree = false;
                return;
            }

            if (target.closest('.sw-flow-trigger') === null) {
                if (target.closest('svg')) {
                    return;
                }

                this.displayTree = false;
            }
        },

        onFocusTrigger() {
            this.showTree();
        },

        showTree() {
            this.displayTree = true;
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
                this.events = this.getEventsTree(events);
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
    },
});
