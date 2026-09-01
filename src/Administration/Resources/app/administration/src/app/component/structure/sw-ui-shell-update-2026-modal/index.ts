/**
 * @sw-package framework
 */
import { MtBadge, MtModal, MtModalRoot } from '@shopware-ag/meteor-component-library';
import type { Theme } from '@shopware-ag/meteor-component-library';
import useTheme from 'src/app/composables/use-theme';
import template from './sw-ui-shell-update-2026-modal.html.twig';
import './sw-ui-shell-update-2026-modal.scss';

type UiShellUpdate2026Page = {
    id: string;
    headline: string;
    descriptionKey: string;
    pinnedSplit?: number;
    hasThemeSelect?: boolean;
    badge?: string;
};

const SPLIT_PROPERTY = '--sw-ui-shell-update-2026-modal-split';
const CENTER_SPLIT_POSITION = 50;
// Mirrors the transition duration of __compare--eased in the stylesheet.
const SPLIT_EASE_DURATION = 300;

// Captured once on the press: moves apply deltas, and the width cannot change mid-drag.
type DragState = {
    pointerId: number;
    originX: number;
    originOffset: number;
    width: number;
    position: number;
};

/**
 * The release that ships the new navigation: shops and users from before it ran the old one.
 *
 * @private
 * @deprecated tag:v6.9.0 - Will be removed together with the one-time ui-shell-update-2026 announcement modal
 */
export const NEW_NAVIGATION_RELEASE_DATE = '2026-10-05';

/**
 * @private
 * @deprecated tag:v6.9.0 - Will be removed together with the one-time ui-shell-update-2026 announcement modal
 */
export const UI_SHELL_UPDATE_2026_SEEN_CONFIG_KEY = 'core.uiShellUpdate2026ModalSeen';

type ContextSettings = {
    firstMigrationDate?: string | null;
};

/**
 * @private
 * @deprecated tag:v6.9.0 - One-time announcement modal for the 2026 UI shell update; will be removed
 * together with its template, styles, specs, snippets, and the static images under
 * static/img/ui-shell-update-2026/
 */
export default Shopware.Component.wrapComponentConfig({
    name: 'sw-ui-shell-update-2026-modal',
    template,

    components: {
        MtBadge,
        MtModal,
        MtModalRoot,
    },

    mixins: [
        Shopware.Mixin.getByName('notification'),
    ],

    data(): {
        isOpen: boolean;
        currentPage: number;
        splitPosition: number | null;
        hintHandoffTimeout: number | null;
        isSplitEased: boolean;
        drag: DragState | null;
        hasRecordedSeen: boolean;
    } {
        return {
            isOpen: false,
            currentPage: 0,
            hintHandoffTimeout: null,
            isSplitEased: false,
            splitPosition: null,
            drag: null,
            hasRecordedSeen: false,
        };
    },

    computed: {
        pages(): UiShellUpdate2026Page[] {
            return [
                {
                    id: 'admin-navigation',
                    headline: this.$t('sw-ui-shell-update-2026-modal.pages.adminNavigation.headline'),
                    descriptionKey: 'sw-ui-shell-update-2026-modal.pages.adminNavigation.description',
                },
                {
                    id: 'dark-mode',
                    headline: this.$t('sw-ui-shell-update-2026-modal.pages.darkMode.headline'),
                    descriptionKey: 'sw-ui-shell-update-2026-modal.pages.darkMode.description',
                    pinnedSplit: 100,
                    hasThemeSelect: true,
                    badge: this.$t('sw-ui-shell-update-2026-modal.pages.darkMode.badge'),
                },
            ];
        },

        activePage(): UiShellUpdate2026Page {
            return this.pages[this.currentPage];
        },

        isFirstPage(): boolean {
            return this.currentPage === 0;
        },

        isLastPage(): boolean {
            return this.currentPage === this.pages.length - 1;
        },

        isSplitPinned(): boolean {
            return this.activePage.pinnedSplit !== undefined;
        },

        isDragging(): boolean {
            return this.drag !== null;
        },

        userTheme: {
            get(): Theme {
                return useTheme().theme.value;
            },
            set(theme: Theme) {
                this.onThemeChange(theme);
            },
        },

        // Only the resting position is bound; the drag writes straight to the element.
        compareStyle(): Record<string, string> {
            if (this.splitPosition === null) {
                return {};
            }

            return { [SPLIT_PROPERTY]: `${this.splitPosition}%` };
        },
    },

    watch: {
        // Paging is the only thing that re-centers: a dragged position is deliberate.
        currentPage() {
            this.drag = null;
            this.clearHintHandoff();

            const pinnedSplit = this.activePage.pinnedSplit;

            this.isSplitEased = true;

            if (pinnedSplit !== undefined) {
                this.splitPosition = pinnedSplit;

                return;
            }

            // Hand the property to the idle hint only after the slide arrives: an animation cuts a transition short.
            this.splitPosition = CENTER_SPLIT_POSITION;

            this.hintHandoffTimeout = window.setTimeout(() => {
                this.hintHandoffTimeout = null;
                this.isSplitEased = false;
                this.splitPosition = null;
            }, SPLIT_EASE_DURATION);
        },
    },

    created() {
        this.createdComponent();
    },

    beforeUnmount() {
        this.beforeUnmountComponent();
    },

    methods: {
        createdComponent() {
            void this.resolveVisibility();
        },

        beforeUnmountComponent() {
            this.clearHintHandoff();
        },

        async resolveVisibility() {
            // TESTING: trigger conditions disabled so the modal always opens.
            // if (!this.isIntendedAudience()) {
            //     return;
            // }
            //
            // if (await this.hasSeenModal()) {
            //     return;
            // }

            this.isOpen = true;
        },

        isIntendedAudience(): boolean {
            if (new Date() < new Date(NEW_NAVIGATION_RELEASE_DATE)) {
                return false;
            }

            if (Shopware.Store.get('context').app.firstRunWizard === true) {
                return false;
            }

            if (!this.isExistingShop()) {
                return false;
            }

            return this.isExistingUser();
        },

        // The date of its very first migration identifies a shop that ran the old navigation.
        isExistingShop(): boolean {
            const settings = Shopware.Store.get('context').app.config.settings as ContextSettings | undefined;

            return this.isBeforeRelease(settings?.firstMigrationDate);
        },

        // An old shop can still have brand new admin users, and those never saw it either.
        isExistingUser(): boolean {
            const currentUser = Shopware.Store.get('session').currentUser as Record<string, unknown> | null;

            return this.isBeforeRelease(currentUser?.createdAt);
        },

        isBeforeRelease(value: unknown): boolean {
            if (typeof value !== 'string' || value === '') {
                return false;
            }

            const date = new Date(value);

            if (Number.isNaN(date.getTime())) {
                return false;
            }

            return date < new Date(NEW_NAVIGATION_RELEASE_DATE);
        },

        async hasSeenModal(): Promise<boolean> {
            const response = await Shopware.Service('userConfigService').search([UI_SHELL_UPDATE_2026_SEEN_CONFIG_KEY]);
            const value = response?.data?.[UI_SHELL_UPDATE_2026_SEEN_CONFIG_KEY] as { seen?: unknown } | undefined;

            return value?.seen === true;
        },

        async markModalSeen(): Promise<void> {
            await Shopware.Service('userConfigService').upsert({
                [UI_SHELL_UPDATE_2026_SEEN_CONFIG_KEY]: { seen: true },
            });
        },

        onModalChange(isOpen: boolean) {
            this.isOpen = isOpen;

            if (!isOpen) {
                this.drag = null;
                this.recordSeen();
            }
        },

        // Closing reaches this twice: onFinish clears isOpen and MtModalRoot reports the same change back.
        recordSeen() {
            if (this.hasRecordedSeen) {
                return;
            }

            this.hasRecordedSeen = true;

            // TESTING: do not persist the seen flag while the trigger is forced open.
            // this.markModalSeen().catch(() => {
            //     this.createNotificationError({
            //         message: this.$t('sw-ui-shell-update-2026-modal.seenSaveError'),
            //     });
            // });
        },

        onPreviousPage() {
            if (!this.isFirstPage) {
                this.currentPage -= 1;
            }
        },

        onNextPage() {
            if (!this.isLastPage) {
                this.currentPage += 1;
            }
        },

        onFinish() {
            this.isOpen = false;

            this.recordSeen();
        },

        onThemeChange(theme: Theme) {
            useTheme()
                .saveUserTheme(theme)
                .catch(() => {
                    this.createNotificationError({
                        message: this.$t('sw-ui-shell-update-2026-modal.pages.darkMode.themeSaveError'),
                    });
                });
        },

        onDragStart(event: PointerEvent) {
            if (this.drag !== null || event.button !== 0 || this.isSplitPinned) {
                return;
            }

            const compare = this.$refs.compare as HTMLElement | undefined;
            const width = compare?.getBoundingClientRect().width ?? 0;

            if (!compare || width === 0) {
                return;
            }

            // Stops the browser from starting a text selection alongside the drag.
            event.preventDefault();

            // Follows the pointer anywhere until release and dies with the element mid-gesture.
            (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);

            const position = this.currentSplitOf(compare);

            this.drag = {
                pointerId: event.pointerId,
                originX: event.clientX,
                originOffset: (position / 100) * width,
                width,
                position,
            };

            // Committed through the render so all three land in one frame: an animation outranks an inline write.
            this.clearHintHandoff();
            this.isSplitEased = false;
            this.splitPosition = position;
        },

        // Written straight to the element, so dragging does not re-render on every move.
        onDragMove(event: PointerEvent) {
            if (this.drag === null || event.pointerId !== this.drag.pointerId) {
                return;
            }

            const compare = this.$refs.compare as HTMLElement | undefined;

            if (!compare) {
                return;
            }

            this.drag.position = this.dragPositionOf(event, this.drag);

            compare.style.setProperty(SPLIT_PROPERTY, `${this.drag.position}%`);
        },

        onDragEnd(event: PointerEvent) {
            if (this.drag === null || event.pointerId !== this.drag.pointerId) {
                return;
            }

            if (!this.isSplitPinned) {
                this.splitPosition = this.drag.position;
            }

            this.drag = null;
        },

        clearHintHandoff() {
            if (this.hintHandoffTimeout !== null) {
                window.clearTimeout(this.hintHandoffTimeout);
                this.hintHandoffTimeout = null;
            }
        },

        // Grabbing mid-animation must read the live computed value, not the bound state.
        currentSplitOf(element: HTMLElement): number {
            const computed = Number.parseFloat(window.getComputedStyle(element).getPropertyValue(SPLIT_PROPERTY));

            if (Number.isFinite(computed)) {
                return computed;
            }

            return this.splitPosition ?? CENTER_SPLIT_POSITION;
        },

        // Snapped to whole pixels so the underlying image cannot bleed through the seam.
        dragPositionOf(event: { clientX: number }, drag: DragState): number {
            const offset = Math.min(drag.width, Math.max(0, Math.round(drag.originOffset + event.clientX - drag.originX)));

            return (offset * 100) / drag.width;
        },

        imageSrc(fileName: string): string {
            return Shopware.Filter.getByName('asset')(
                `/administration/administration/static/img/ui-shell-update-2026/${fileName}`,
            );
        },
    },
});
