import {
    MtActionMenu,
    MtActionMenuItem,
    MtButton,
    MtDropdownMenuPortal,
    MtDropdownMenuRoot,
    MtDropdownMenuTrigger,
    MtIcon,
    MtLink,
    MtStatusDot,
} from '@shopware-ag/meteor-component-library';
import type { PropType } from 'vue';
import type { ContentSystemBreakpoint } from 'src/core/service/api/content-system-element-type.api.service';
import template from './sw-experience-studio-breakpoint-select.html.twig';
import './sw-experience-studio-breakpoint-select.scss';

/** @private */
export const BASE_BREAKPOINT = 'lg' satisfies ContentSystemBreakpoint;

const sizes = [
    { id: 'xs', labelSnippet: 'sw-experience-studio.breakpointSelect.extraSmall', icon: 'regular-mobile' },
    { id: 'sm', labelSnippet: 'sw-experience-studio.detail.toolbar.viewportMobile', short: true, icon: 'regular-mobile' },
    { id: 'md', labelSnippet: 'sw-experience-studio.detail.toolbar.viewportTablet', short: true, icon: 'regular-tablet' },
    {
        id: BASE_BREAKPOINT,
        labelSnippet: 'sw-experience-studio.detail.toolbar.viewportDesktop',
        short: true,
        icon: 'regular-desktop',
    },
    { id: 'xl', labelSnippet: 'sw-experience-studio.breakpointSelect.extraLarge', icon: 'regular-desktop' },
    { id: 'xxl', labelSnippet: 'sw-experience-studio.breakpointSelect.ultrawide', icon: 'regular-desktop' },
] as const satisfies readonly { id: ContentSystemBreakpoint; labelSnippet: string; icon: string; short?: true }[];

type Size = (typeof sizes)[number];
/** @private */
export const orderedBreakpoints: readonly ContentSystemBreakpoint[] = sizes.map(({ id }) => id);

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    components: {
        MtActionMenu,
        MtActionMenuItem,
        MtButton,
        MtDropdownMenuPortal,
        MtDropdownMenuRoot,
        MtDropdownMenuTrigger,
        MtIcon,
        MtLink,
        MtStatusDot,
    },

    props: {
        modelValue: { type: String as PropType<ContentSystemBreakpoint>, required: true },
        overrides: { type: Array as PropType<ContentSystemBreakpoint[]>, required: true },
        canReset: { type: Boolean, default: false },
        invalidBreakpoints: { type: Array as PropType<ContentSystemBreakpoint[]>, default: () => [] },
    },

    emits: ['update:modelValue', 'reset'],

    data() {
        return {
            open: false,
            expanded: false,
            closedOnBlur: false,
            windowBlurHandler: null as (() => void) | null,
            base: BASE_BREAKPOINT,
        };
    },

    computed: {
        showReset(): boolean {
            return this.canReset && this.modelValue !== BASE_BREAKPOINT && this.overrides.includes(this.modelValue);
        },
        selectedSize(): Size {
            return sizes.find(({ id }) => id === this.modelValue) ?? sizes[3];
        },
        triggerLabel(): string {
            return this.modelValue === this.base
                ? this.$t('sw-experience-studio.breakpointSelect.base')
                : this.selectedSize.id.toUpperCase();
        },
        shortList(): Size[] {
            return sizes.filter(
                (size) => 'short' in size || size.id === this.modelValue || this.overrides.includes(size.id),
            );
        },
        visibleSizes(): readonly Size[] {
            return this.expanded ? sizes : this.shortList;
        },
        hiddenCount(): number {
            return sizes.length - this.shortList.length;
        },
        overrideHint(): string {
            if (this.overrides.length === 1) {
                return this.$t('sw-experience-studio.breakpointSelect.singleOverride', {
                    size: this.overrides[0].toUpperCase(),
                });
            }

            return this.$t('sw-experience-studio.breakpointSelect.overrideCount', { count: this.overrides.length });
        },
    },

    beforeUnmount() {
        this.removeWindowBlurHandler();
    },

    methods: {
        reset(): void {
            if (!this.showReset) return;

            (this.$el as HTMLElement)
                .querySelector<HTMLButtonElement>('.sw-experience-studio-breakpoint-select__trigger')
                ?.focus();
            this.$emit('reset');
        },
        onOpenChange(open: boolean): void {
            if (this.open === open) return;

            this.open = open;
            if (open) {
                this.closedOnBlur = false;
                this.windowBlurHandler = () => {
                    this.closedOnBlur = true;
                    this.onOpenChange(false);
                };
                window.addEventListener('blur', this.windowBlurHandler);
                return;
            }

            this.expanded = false;
            this.removeWindowBlurHandler();
        },
        removeWindowBlurHandler(): void {
            if (!this.windowBlurHandler) return;

            window.removeEventListener('blur', this.windowBlurHandler);
            this.windowBlurHandler = null;
        },
        onCloseAutoFocus(event: Event): void {
            if (this.closedOnBlur) event.preventDefault();
        },
        getLabel(size: Size): string {
            return this.$t(size.labelSnippet);
        },
        toggleExpanded(event: Event): void {
            event.preventDefault();
            this.expanded = !this.expanded;
        },
    },
});
