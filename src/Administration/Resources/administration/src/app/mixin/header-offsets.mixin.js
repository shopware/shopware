import { Mixin } from 'src/core/shopware';
import dom from 'src/core/service/utils/dom.utils';

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
        this.$root.$on('swSidebarMounted', (sidebarWidth) => {
            this.sidebarOffset = sidebarWidth;
        });

        this.$root.$on('swSidebarDestroyed', () => {
            this.sidebarOffset = 0;
        });
    },

    methods: {
        setScrollbarOffset() {
            const contentEl = document.querySelector('.sw-card-view__content');

            if (contentEl !== null) {
                this.scrollbarOffset = dom.getScrollbarWidth(contentEl);
            }
        }
    }
});
