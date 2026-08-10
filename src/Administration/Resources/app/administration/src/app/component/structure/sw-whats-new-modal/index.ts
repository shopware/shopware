/**
 * @sw-package framework
 */
import { MtBadge, MtModal, MtModalRoot } from '@shopware-ag/meteor-component-library';
import template from './sw-whats-new-modal.html.twig';
import './sw-whats-new-modal.scss';

type WhatsNewPage = {
    id: string;
    headline: string;
    descriptionKey: string;
    videoSrc: string;
    placeholderIcon: string;
    badge?: string;
};

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

    data(): {
        isOpen: boolean;
        currentPage: number;
        failedVideos: string[];
    } {
        return {
            isOpen: false,
            currentPage: 0,
            failedVideos: [],
        };
    },

    computed: {
        pages(): WhatsNewPage[] {
            const assetFilter = Shopware.Filter.getByName('asset');

            return [
                {
                    id: 'admin-navigation',
                    headline: this.$t('sw-whats-new-modal.pages.adminNavigation.headline'),
                    descriptionKey: 'sw-whats-new-modal.pages.adminNavigation.description',
                    videoSrc: assetFilter('/administration/administration/static/video/whats-new/admin-navigation.mp4'),
                    placeholderIcon: 'regular-sidebar',
                },
                {
                    id: 'dark-mode',
                    headline: this.$t('sw-whats-new-modal.pages.darkMode.headline'),
                    descriptionKey: 'sw-whats-new-modal.pages.darkMode.description',
                    videoSrc: assetFilter('/administration/administration/static/video/whats-new/dark-mode.mp4'),
                    placeholderIcon: 'regular-moon',
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

        showActiveVideo(): boolean {
            return !this.failedVideos.includes(this.activePage.id);
        },
    },

    created() {
        if (Shopware.Store.get('context').app.firstRunWizard !== true) {
            this.isOpen = true;
        }
    },

    methods: {
        onModalChange(isOpen: boolean) {
            this.isOpen = isOpen;
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
        },

        onVideoError() {
            if (this.showActiveVideo) {
                this.failedVideos.push(this.activePage.id);
            }
        },
    },
});
