import template from './sw-select-base.html.twig';
import './sw-select-base.scss';

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Base component for creating new select components. Uses sw-field base components as basic structure.
 * @example-type code-only
 */
export default {
    template,

    inheritAttrs: false,

    emits: ['select-expanded', 'select-collapsed', 'clear'],

    props: {
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },

        /**
         * Controls visibility of the clear button.
         * When undefined, defaults to true if not required, false if required.
         * Explicit true/false overrides this default behavior.
         * @see isClearable computed property
         */
        showClearableButton: {
            type: Boolean,
            required: false,
            default: undefined,
        },

        size: {
            type: String,
            required: false,
            default: 'default',
        },
    },

    data() {
        return {
            expanded: false,
            hasFocus: false,
        };
    },

    computed: {
        swFieldClasses() {
            return { 'has--focus': this.expanded || this.hasFocus };
        },

        isClearable() {
            // If explicitly set, use the provided value
            if (this.showClearableButton !== undefined) {
                return this.showClearableButton;
            }

            // Default: clearable when not required
            // '' case is for empty attribute like <form-field required> which should be treated as true
            return !this.$attrs.required && this.$attrs.required !== '';
        },
    },

    mounted() {
        this.onMounted();
    },

    beforeUnmount() {
        this.onBeforeUnmount();
    },

    methods: {
        onMounted() {
            document.addEventListener('keydown', this.handleKeydown);
        },

        onBeforeUnmount() {
            document.removeEventListener('keydown', this.handleKeydown);
        },

        handleKeydown(event) {
            if (!this.expanded) {
                return;
            }

            // Handle escape key
            if (event.key === 'Escape' || event.key === 'Esc') {
                this.collapse();
            }
        },

        toggleExpand() {
            if (!this.expanded) {
                this.expand();
            } else {
                this.collapse();
            }
        },

        expand() {
            if (this.expanded) {
                return;
            }

            if (this.disabled) {
                return;
            }

            this.expanded = true;
            document.addEventListener('click', this.listenToClickOutside);
            this.$emit('select-expanded');
        },

        /**
         * Opens the select from the keyboard, like a native select.
         * A printable character opens it without being swallowed, so it starts the search.
         */
        expandByKeyboard(event) {
            if (this.expanded || event.ctrlKey || event.metaKey) {
                return;
            }

            const isOnSelection = event.target === this.$refs.selectWrapper;

            if (['ArrowDown', 'ArrowUp'].includes(event.key) || (isOnSelection && ['Enter', ' '].includes(event.key))) {
                // The result list mounts before this event reaches the document, where it would pick an item
                event.preventDefault();
                event.stopPropagation();
                this.expand();
                return;
            }

            if (event.key.length === 1 && !event.altKey) {
                this.expand();
            }
        },

        collapse(event) {
            if (!this.expanded) {
                return;
            }

            const hadFocus = this.$refs.selectWrapper?.contains(document.activeElement) ?? false;

            document.removeEventListener('click', this.listenToClickOutside);
            this.expanded = false;

            // do not let clearable button trigger change event
            if (event?.target?.dataset.clearableButton === undefined) {
                this.$emit('select-collapsed');
            }

            // TAB and SHIFT+TAB then move on from the select itself, and ESC keeps the focus on it
            if (hadFocus) {
                this.$refs.selectWrapper.focus();
            }
        },

        listenToClickOutside(event) {
            const target = event.target;
            const clickIsInsideSelect = target instanceof Node && this.$el.contains(target);

            // Borderline clicks can target the body even while the pointer is still over the select.
            // Non-layout environments like jsdom do not implement the hit-test fallback.
            const clickedElementStackContainsSelect =
                typeof document.elementsFromPoint === 'function' &&
                document
                    .elementsFromPoint(event.clientX, event.clientY)
                    .some((element) => element === this.$el || this.$el.contains(element));

            if (!clickIsInsideSelect && !clickedElementStackContainsSelect) {
                this.collapse();
            }
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed. Use `Element.contains()` instead.
         */
        computePath(event) {
            const path = [];
            let target = event.target;

            while (target) {
                path.push(target);
                target = target.parentElement;
            }

            return path;
        },

        emitClear() {
            this.$emit('clear');
        },
    },
};
