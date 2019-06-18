import template from './sw-shortcut-overview.html.twig';
import './sw-shortcut-overview.scss';

export default {
    name: 'sw-shortcut-overview',
    template,

    shortcuts: {
        '?': 'onOpenShortcutOverviewModal'
    },

    data() {
        return {
            showShortcutOverviewModal: false
        };
    },

    computed: {
        tooltipOpenModal() {
            return {
                message: this.$tc('sw-shortcut-overview.iconTooltip', 0, { key: '?' }),
                appearance: 'light'
            };
        }
    },

    methods: {
        onOpenShortcutOverviewModal() {
            this.showShortcutOverviewModal = true;
        },

        onCloseShortcutOverviewModal() {
            this.showShortcutOverviewModal = false;
        }
    }
};
