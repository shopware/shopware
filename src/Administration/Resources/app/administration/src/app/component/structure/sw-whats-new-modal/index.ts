/**
 * @sw-package framework
 */
import { MtBadge, MtModal, MtModalRoot } from '@shopware-ag/meteor-component-library';
import type { Theme } from '@shopware-ag/meteor-component-library';
import useTheme from 'src/app/composables/use-theme';
import template from './sw-whats-new-modal.html.twig';
import './sw-whats-new-modal.scss';

// Both pages share the same imagery; only how much of it is revealed differs.
type WhatsNewPage = {
    id: string;
    headline: string;
    descriptionKey: string;
    // Fixes the reveal at one position instead of letting the mouse drive it.
    pinnedSplit?: number;
    // Set only on pages that let the theme be changed from here.
    hasThemeSelect?: boolean;
    badge?: string;
};

const SPLIT_PROPERTY = '--sw-whats-new-modal-split';
const CENTER_SPLIT_POSITION = 50;
// Mirrors the transition duration of __compare--eased in the stylesheet.
const SPLIT_EASE_DURATION = 300;
// How far the pointer has to travel before a press counts as a drag rather than a click.
const DRAG_START_THRESHOLD = 3;

// eslint-disable-next-line no-warning-comments
// @todo PLACEHOLDER DATE - set this to the release that ships the new navigation.
// Shops first migrated on or after it never ran the old navigation, so they have
// nothing to compare against. The far future placeholder keeps every existing shop
// eligible until the real date is known, which also makes the modal easy to try out.
const NEW_NAVIGATION_RELEASE_DATE = '2099-01-01';

// eslint-disable-next-line no-warning-comments
// @todo TESTING - set to false before this ships.
/**
 * Reopens the modal on every load while the design is still being reviewed. The flag is
 * still recorded, so flipping this back to false is all it takes to show it exactly once.
 *
 * @private
 */
export const IGNORE_SEEN_FLAG = true;

/**
 * user_config key recording that the current user has been shown the modal. Stored
 * server side rather than in the browser, so it follows the account.
 *
 * @private
 */
export const WHATS_NEW_SEEN_CONFIG_KEY = 'core.whatsNewModalSeen';

type ContextSettings = {
    firstMigrationDate?: string | null;
};

function assetPath(fileName: string): string {
    return Shopware.Filter.getByName('asset')(`/administration/administration/static/img/whats-new/${fileName}`);
}

function isBeforeRelease(value: unknown): boolean {
    if (typeof value !== 'string' || value === '') {
        return false;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return false;
    }

    return date < new Date(NEW_NAVIGATION_RELEASE_DATE);
}

/**
 * Only shops that already ran on the previous navigation should be told about the
 * rework, which the date of their very first migration identifies.
 */
function isExistingShop(): boolean {
    const settings = Shopware.Store.get('context').app.config.settings as ContextSettings | undefined;

    return isBeforeRelease(settings?.firstMigrationDate);
}

/**
 * An old shop can still have brand new admin users, and those never saw the previous
 * navigation either.
 */
function isExistingUser(): boolean {
    const currentUser = Shopware.Store.get('session').currentUser as Record<string, unknown> | null;

    return isBeforeRelease(currentUser?.createdAt);
}

async function hasSeenModal(): Promise<boolean> {
    const response = await Shopware.Service('userConfigService').search([WHATS_NEW_SEEN_CONFIG_KEY]);
    const value = response?.data?.[WHATS_NEW_SEEN_CONFIG_KEY] as { seen?: unknown } | undefined;

    return value?.seen === true;
}

/**
 * The user config is scoped to the current user by the API, so the flag needs no key of
 * its own to tell accounts apart.
 *
 * @private
 */
export async function markModalSeen(): Promise<void> {
    await Shopware.Service('userConfigService').upsert({
        [WHATS_NEW_SEEN_CONFIG_KEY]: { seen: true },
    });
}

function isIntendedAudience(): boolean {
    if (Shopware.Store.get('context').app.firstRunWizard === true) {
        return false;
    }

    if (!isExistingShop()) {
        return false;
    }

    return isExistingUser();
}

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    name: 'sw-whats-new-modal',
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
        isDragging: boolean;
        // Both are only read while a drag is running, so neither is rendered from.
        draggedPosition: number | null;
        dragOriginX: number | null;
        hasRecordedSeen: boolean;
    } {
        return {
            isOpen: false,
            currentPage: 0,
            hintHandoffTimeout: null,
            isSplitEased: false,
            splitPosition: null,
            isDragging: false,
            draggedPosition: null,
            dragOriginX: null,
            hasRecordedSeen: false,
        };
    },

    computed: {
        backgroundSrc(): string {
            return assetPath('background-light.jpg');
        },

        darkBackgroundSrc(): string {
            return assetPath('background-dark.jpg');
        },

        beforeSrc(): string {
            return assetPath('sidebar-before.jpg');
        },

        afterSrc(): string {
            return assetPath('sidebar-after.jpg');
        },

        darkAfterSrc(): string {
            return assetPath('sidebar-after-dark.jpg');
        },

        pages(): WhatsNewPage[] {
            return [
                {
                    id: 'admin-navigation',
                    headline: this.$t('sw-whats-new-modal.pages.adminNavigation.headline'),
                    descriptionKey: 'sw-whats-new-modal.pages.adminNavigation.description',
                },
                {
                    id: 'dark-mode',
                    headline: this.$t('sw-whats-new-modal.pages.darkMode.headline'),
                    descriptionKey: 'sw-whats-new-modal.pages.darkMode.description',
                    // Slides all the way over to the reworked navigation and stays there.
                    pinnedSplit: 100,
                    hasThemeSelect: true,
                    badge: this.$t('sw-whats-new-modal.pages.darkMode.badge'),
                },
            ];
        },

        activePage(): WhatsNewPage {
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

        userTheme: {
            get(): Theme {
                return useTheme().theme.value;
            },
            set(theme: Theme) {
                this.onThemeChange(theme);
            },
        },

        // Only the resting position is bound, so it survives the re-render that paging
        // causes. The drag itself is written straight to the element, see onDragMove.
        compareStyle(): Record<string, string> {
            if (this.splitPosition === null) {
                return {};
            }

            return { [SPLIT_PROPERTY]: `${this.splitPosition}%` };
        },
    },

    watch: {
        // A pinned page slides the reveal over and holds it; any other page hands control
        // back to the pointer, centered, with the idle hint inviting a drag again. This is
        // the only thing that re-centers: a dragged position is deliberate and stays put.
        currentPage() {
            this.clearHintHandoff();

            const pinnedSplit = this.activePage.pinnedSplit;

            this.isSplitEased = true;

            if (pinnedSplit !== undefined) {
                this.splitPosition = pinnedSplit;

                return;
            }

            // Leaving a pinned page eases the reveal back to the middle. The idle hint can
            // only take the property over once that has arrived, because an animation wins
            // against a transition and would cut it short.
            this.splitPosition = CENTER_SPLIT_POSITION;

            this.hintHandoffTimeout = window.setTimeout(() => {
                this.hintHandoffTimeout = null;
                this.isSplitEased = false;
                this.splitPosition = null;
            }, SPLIT_EASE_DURATION);
        },
    },

    created() {
        void this.resolveVisibility();
    },

    beforeUnmount() {
        this.clearHintHandoff();
        this.stopListeningForDrag();
    },

    methods: {
        // The seen flag lives on the server, so the audience can only be settled once the
        // request comes back.
        async resolveVisibility() {
            if (!isIntendedAudience()) {
                return;
            }

            if (!IGNORE_SEEN_FLAG && (await hasSeenModal())) {
                return;
            }

            this.isOpen = true;
        },

        onModalChange(isOpen: boolean) {
            this.isOpen = isOpen;

            if (!isOpen) {
                this.recordSeen();
            }
        },

        // Guarded because closing reaches this from two directions at once: the finish
        // button clears isOpen itself and MtModalRoot then reports the same change back.
        recordSeen() {
            if (this.hasRecordedSeen) {
                return;
            }

            this.hasRecordedSeen = true;

            markModalSeen().catch(() => {
                // Losing the flag only means the modal is offered again, so it stays quiet.
            });
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

        // Applies straight away and persists to the user config, so there is nothing to
        // confirm: the modal has no save of its own to hang the change on.
        onThemeChange(theme: Theme) {
            useTheme()
                .saveUserTheme(theme)
                .catch(() => {
                    this.createNotificationError({
                        message: this.$t('sw-whats-new-modal.pages.darkMode.themeSaveError'),
                    });
                });
        },

        // Only arms the drag: a press on its own never moves the reveal, so clicking the
        // images cannot yank the seam across and nothing has to be eased to cover a jump.
        onDragStart(event: PointerEvent) {
            if (this.isSplitPinned) {
                return;
            }

            // Stops the browser from starting its own drag on the images underneath.
            event.preventDefault();

            this.isDragging = true;
            this.dragOriginX = event.clientX;

            // On window rather than the element, so the reveal keeps following a pointer that
            // has been dragged outside the images and still stops on release out there.
            // Passing the methods unbound is safe: Vue binds options-API methods to the instance.
            /* eslint-disable @typescript-eslint/unbound-method */
            window.addEventListener('pointermove', this.onDragMove);
            window.addEventListener('pointerup', this.onDragEnd);
            window.addEventListener('pointercancel', this.onDragEnd);
            /* eslint-enable @typescript-eslint/unbound-method */
        },

        // Written straight to the element rather than through component state, so dragging
        // does not re-render the modal on every pointer move. Resolved from the ref on every
        // move rather than captured on press, so it cannot end up writing to a stale node.
        onDragMove(event: PointerEvent) {
            const element = this.$refs.compare as HTMLElement | undefined;

            if (!element) {
                return;
            }

            // A null dragged position means this drag has not moved anything yet, so the press
            // is still only a click until the pointer has really travelled.
            const hasTakenOver = this.draggedPosition !== null;

            if (!hasTakenOver && !this.hasPassedDragThreshold(event)) {
                return;
            }

            const position = this.splitPositionOf(event, element);

            if (position === null) {
                return;
            }

            // The move that takes the drag over goes through the render, so that stopping the
            // idle hint and dropping any paging slide land in the same frame as the position.
            // An inline write cannot do that: an animation outranks it, and a transition still
            // in effect would ease it. Every later move is written straight to the element,
            // which is what keeps the drag itself immediate and free of re-renders.
            if (!hasTakenOver) {
                this.clearHintHandoff();

                this.isSplitEased = false;
                this.draggedPosition = position;
                this.splitPosition = position;

                return;
            }

            this.draggedPosition = position;

            element.style.setProperty(SPLIT_PROPERTY, `${position}%`);
        },

        hasPassedDragThreshold(event: PointerEvent): boolean {
            if (this.dragOriginX === null) {
                return true;
            }

            return Math.abs(event.clientX - this.dragOriginX) >= DRAG_START_THRESHOLD;
        },

        // The reveal stays wherever it was let go of; only paging ever re-centers it. A press
        // that never became a drag leaves everything as it was.
        onDragEnd() {
            this.isDragging = false;
            this.dragOriginX = null;

            if (this.draggedPosition !== null) {
                this.splitPosition = this.draggedPosition;
                this.draggedPosition = null;
            }

            this.stopListeningForDrag();
        },

        stopListeningForDrag() {
            /* eslint-disable @typescript-eslint/unbound-method */
            window.removeEventListener('pointermove', this.onDragMove);
            window.removeEventListener('pointerup', this.onDragEnd);
            window.removeEventListener('pointercancel', this.onDragEnd);
            /* eslint-enable @typescript-eslint/unbound-method */
        },

        clearHintHandoff() {
            if (this.hintHandoffTimeout !== null) {
                window.clearTimeout(this.hintHandoffTimeout);
                this.hintHandoffTimeout = null;
            }
        },

        // Snapped to whole pixels: a fractional edge only partially covers its boundary
        // column, which lets the image underneath bleed through the seam.
        splitPositionOf(event: { clientX: number }, element: HTMLElement): number | null {
            const { left, width } = element.getBoundingClientRect();

            if (width === 0) {
                return null;
            }

            const offset = Math.min(width, Math.max(0, Math.round(event.clientX - left)));

            return (offset / width) * 100;
        },
    },
});
