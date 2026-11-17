import template from './sw-select-base.html.twig';
import './sw-select-base.scss';

/**
 * Keys that open a focused, closed select, as in the WAI-ARIA APG select-only combobox.
 * Printable characters open it too, see `expandByKeyboard`.
 */
const OPENING_KEYS = [
    'ArrowDown',
    'ArrowUp',
    'Enter',
    ' ',
];

/**
 * Keys that open a focused, closed select and reach the key handlers of its search,
 * where multi selects remove their last value.
 */
const DELETING_KEYS = [
    'Backspace',
    'Delete',
];

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
        };
    },

    computed: {
        swFieldClasses() {
            return { 'has--focus': this.expanded };
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
         * Opens the select on a click anywhere in the field, except on buttons inside it.
         * Listens in the capture phase, because selected values stop their clicks from bubbling.
         *
         * @example <div @click.capture="expandByClick">
         */
        expandByClick(event) {
            const button = event.target instanceof Element ? event.target.closest('button') : null;

            if (button && this.$refs.selectWrapper.contains(button)) {
                return;
            }

            this.expand();
        },

        /**
         * Opens the select from ArrowDown, ArrowUp, Enter or Space, from Backspace or Delete,
         * and from a key that types a character.
         * Only keys on the select itself count, so buttons inside it keep their own keys.
         */
        expandByKeyboard(event) {
            if (this.expanded || event.target !== this.$refs.selectWrapper || event.isComposing) {
                return;
            }

            if (OPENING_KEYS.includes(event.key) && !event.ctrlKey && !event.metaKey) {
                // The result list mounts before this event reaches the document, where it would pick an item
                event.preventDefault();
                event.stopPropagation();
                this.expand();
                return;
            }

            if (DELETING_KEYS.includes(event.key)) {
                event.preventDefault();
                this.expand();
                this.$nextTick(() => this.pressInSearch(event.key));
                return;
            }

            if (this.typesCharacter(event)) {
                // Firefox inserts a typed character only into the element focused during keydown,
                // and the search input gets the focus later, after the select renders open
                event.preventDefault();
                this.expand();
                this.$nextTick(() => this.typeIntoSearch(event.key));
            }
        },

        /**
         * Returns the search input of this select once a variant moved the focus into it, or null.
         *
         * @example const search = this.getFocusedSearch();
         */
        getFocusedSearch() {
            const search = document.activeElement;

            if (!(search instanceof HTMLInputElement) || !this.$refs.selectWrapper?.contains(search)) {
                return null;
            }

            return search;
        },

        /**
         * Types a character into the focused search input of this select, as if the user typed it.
         * Runs after the select opened, once the variant moved the focus into its search.
         *
         * @example this.$nextTick(() => this.typeIntoSearch('a'));
         */
        typeIntoSearch(character) {
            const search = this.getFocusedSearch();

            if (!search || search.readOnly || search.disabled) {
                return;
            }

            search.setRangeText(character, search.selectionStart ?? 0, search.selectionEnd ?? 0, 'end');
            search.dispatchEvent(new Event('input', { bubbles: true }));
        },

        /**
         * Hands Backspace or Delete on to the key handlers of the focused search input of this select,
         * so multi selects remove their last value. The search text stays as it is.
         * Runs after the select opened, once the variant moved the focus into its search.
         *
         * @example this.$nextTick(() => this.pressInSearch('Backspace'));
         */
        pressInSearch(key) {
            this.getFocusedSearch()?.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true }));
        },

        /**
         * Tells a key that types a character apart from a shortcut.
         * AltGr reports CTRL+ALT on Windows and still types.
         * ALT alone types only on macOS (Option); elsewhere it is the system key of the Administration shortcuts.
         *
         * @example this.typesCharacter(new KeyboardEvent('keydown', { key: 'a' })) // true
         */
        typesCharacter(event) {
            if (event.key.length !== 1 || event.metaKey) {
                return false;
            }

            if (event.ctrlKey) {
                return event.altKey;
            }

            return !event.altKey || this.$device.getSystemKey() !== 'ALT';
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
