import { Mixin } from 'src/core/shopware';
import dom from 'src/core/service/utils/dom.utils';

const SIDEBAR_WIDTH = 64;

Mixin.register('header-offsets', {

    data() {
        return {
            scrollBarOffset: 0,
            sidebarOffset: 0
        };
    },

    computed: {
        headerOffsetStyles() {
            return {
                'padding-right': `${this.scrollbarOffset + this.sidebarOffset}px`
            };
        }
    },

    mounted() {
        this.setScrollbarOffset();
    },

    updated() {
        this.setScrollbarOffset();
    },

    created() {
        this.$root.$on('swSidebarMounted', () => {
            this.sidebarOffset = SIDEBAR_WIDTH;
        });

        this.$root.$on('swSidebarDestroyed', () => {
            this.sidebarOffset = 0;
        });
    },

    methods: {
        setScrollbarOffset() {
            const el = document.querySelector('.sw-card-view__content');

            if (el !== null) {
                this.scrollbarOffset = dom.getScrollbarWidth(el);
            }
        }
    }
});
