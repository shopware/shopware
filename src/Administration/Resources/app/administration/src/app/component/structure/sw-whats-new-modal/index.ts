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
const IDLE_RECENTER_DELAY = 3000;
// Mirrors the transition duration of __compare--eased in the stylesheet.
const SPLIT_EASE_DURATION = 300;

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
        recenterTimeout: number | null;
        hintHandoffTimeout: number | null;
        isSplitEased: boolean;
        hasRecordedSeen: boolean;
    } {
        return {
            isOpen: false,
            currentPage: 0,
            recenterTimeout: null,
            hintHandoffTimeout: null,
            isSplitEased: false,
            splitPosition: null,
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
        // causes. Movement itself is written straight to the element, see onCompareMove.
        compareStyle(): Record<string, string> {
            if (this.splitPosition === null) {
                return {};
            }

            return { [SPLIT_PROPERTY]: `${this.splitPosition}%` };
        },
    },

    watch: {
        // A pinned page slides the reveal over and holds it; any other page hands control
        // back to the mouse, centered, with the idle hint inviting interaction again.
        currentPage() {
            this.clearRecenter();
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
        this.clearRecenter();
        this.clearHintHandoff();
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

        // Written straight to the element rather than through component state, so following
        // the cursor does not re-render the modal on every mouse move.
        onCompareMove(event: MouseEvent) {
            if (this.isSplitPinned) {
                return;
            }

            const element = event.currentTarget as HTMLElement;
            const position = this.splitPositionOf(event, element);

            if (position === null) {
                return;
            }

            // Committing the position once is what takes the property away from the idle
            // hint and the eased slide, both of which would otherwise win over the inline
            // value. It also has to agree with the value written below, or the render it
            // triggers would put the reveal back where the slide left it.
            if (this.isSplitEased || this.splitPosition === null) {
                this.clearHintHandoff();

                this.isSplitEased = false;
                this.splitPosition = position;
            }

            element.style.setProperty(SPLIT_PROPERTY, `${position}%`);

            this.scheduleRecenter(element);
        },

        // The reveal keeps the side the cursor left through, and leaving over the top or
        // bottom edge holds the horizontal position. splitPositionOf clamps to the bounds,
        // so an exit past either side lands on 0 or 100 on its own. Committing the resting
        // position to state re-renders once, so it also survives paging.
        onCompareLeave(event: MouseEvent) {
            if (this.isSplitPinned) {
                return;
            }

            const element = event.currentTarget as HTMLElement;
            const position = this.splitPositionOf(event, element);

            if (position === null) {
                return;
            }

            this.splitPosition = position;

            element.style.setProperty(SPLIT_PROPERTY, `${position}%`);

            this.scheduleRecenter(element);
        },

        // Idling anywhere on the images eases the reveal back to the middle, so the modal
        // never rests on a lopsided split.
        scheduleRecenter(element: HTMLElement) {
            this.clearRecenter();

            this.recenterTimeout = window.setTimeout(() => {
                this.recenterTimeout = null;
                this.isSplitEased = true;
                this.splitPosition = CENTER_SPLIT_POSITION;

                element.style.setProperty(SPLIT_PROPERTY, `${CENTER_SPLIT_POSITION}%`);
            }, IDLE_RECENTER_DELAY);
        },

        clearRecenter() {
            if (this.recenterTimeout !== null) {
                window.clearTimeout(this.recenterTimeout);
                this.recenterTimeout = null;
            }
        },

        clearHintHandoff() {
            if (this.hintHandoffTimeout !== null) {
                window.clearTimeout(this.hintHandoffTimeout);
                this.hintHandoffTimeout = null;
            }
        },

        // Snapped to whole pixels: a fractional edge only partially covers its boundary
        // column, which lets the image underneath bleed through the seam.
        splitPositionOf(event: MouseEvent, element: HTMLElement): number | null {
            const { left, width } = element.getBoundingClientRect();

            if (width === 0) {
                return null;
            }

            const offset = Math.min(width, Math.max(0, Math.round(event.clientX - left)));

            return (offset / width) * 100;
        },
    },
});
