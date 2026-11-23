import template from './sw-select-base.html.twig';
import './sw-select-base.scss';

/**
 * What a key does on a select, see `onKeydown`.
 * `open` and `delete` only count on the focused, closed select itself,
 * as in the WAI-ARIA APG select-only combobox.
 * Keys that type a character open it too.
 */
const KEY_ACTIONS = {
    ArrowDown: 'open',
    ArrowUp: 'open',
    Enter: 'open',
    ' ': 'open',
    Backspace: 'delete',
    Delete: 'delete',
    Tab: 'collapse',
    Escape: 'collapse',
};

/**
 * Attributes of the search input inside a select, handed to the variants through the `sw-select-selection` slot.
 * They keep the search out of the tab order and let the base find it.
 */
const SEARCH_INPUT_ATTRS = Object.freeze({
    tabindex: '-1',
    'data-sw-select-search': '',
});

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

    emits: [
        'select-expanded',
        'select-collapsed',
        'clear',
        'delete-key',
    ],

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
            wrapperFocused: false,
        };
    },

    computed: {
        swFieldClasses() {
            return { 'has--focus': this.expanded || this.wrapperFocused };
        },

        searchInputAttrs() {
            return SEARCH_INPUT_ATTRS;
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
         * Handles every key on the select and inside it, see `KEY_ACTIONS`.
         * TAB and ESC close it from anywhere inside.
         * The other keys only count on the closed select itself, so buttons and inputs inside it keep their own keys:
         * ArrowDown, ArrowUp, Enter, Space and typed characters open it,
         * and Backspace and Delete emit `delete-key`, which multi selects answer by removing their last value.
         */
        onKeydown(event) {
            const action = KEY_ACTIONS[event.key];

            if (action === 'collapse') {
                this.collapse(event);
                return;
            }

            if (this.disabled || this.expanded || event.target !== this.$refs.selectWrapper || event.isComposing) {
                return;
            }

            if (action === 'open' && !event.ctrlKey && !event.metaKey) {
                // The result list mounts before this event reaches the document, where it would pick an item
                event.preventDefault();
                event.stopPropagation();
                this.expand();
                return;
            }

            if (action === 'delete') {
                event.preventDefault();
                this.$emit('delete-key');
                return;
            }

            if (this.typesCharacter(event)) {
                // Firefox inserts a typed character only into the element focused during keydown,
                // and the search input gets the focus later, after the select renders open
                event.preventDefault();
                this.expand();
                this.typeIntoSearch(event.key);
            }
        },

        /**
         * Types a character into the search input of this select, as if the user typed it over its text.
         * Runs once the opened select rendered; the search is the input that binds `searchInputAttrs`.
         */
        async typeIntoSearch(character) {
            await this.$nextTick();
            const search = this.$refs.selectWrapper?.querySelector('[data-sw-select-search]');

            if (!(search instanceof HTMLInputElement) || search.readOnly || search.disabled) {
                return;
            }

            search.focus();
            search.setRangeText(character, 0, search.value.length, 'end');
            search.dispatchEvent(new Event('input', { bubbles: true }));
        },

        /**
         * Tells a key that types a character apart from a shortcut.
         * AltGr reports CTRL+ALT on Windows and still types.
         * ALT alone types only on macOS (Option); elsewhere it is the system key of the Administration shortcuts.
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
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                'sw-select-base.computePath() is deprecated. Use `Element.contains()` instead.',
            );

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
