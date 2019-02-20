import template from './sw-side-navigation.twig';
import './sw-side-navigation.scss';

/**
 * @public
 * @description Renders a side navigation. Each item references a route and the item content will be rendered
 * @status ready
 * @example-type static
 * @component-example
 * <sw-side-navigation>
 *
 *     <sw-side-navigation-item :to="{ name: 'sw.explore.index' }">
 *         Explore
 *     </sw-side-navigation-item>
 *
 *     <sw-side-navigation-item to="A link">
 *         My Plugins
 *     </sw-side-navigation-item>
 *
 * </sw-side-navigation>
 */
export default {
    name: 'sw-side-navigation',
    template,

    data() {
        return {
            activeItem: 0
        };
    },

    created() {
        this.updateActiveItem();
    },

    watch: {
        '$route'() {
            this.updateActiveItem();
        }
    },

    computed: {
        sliderStyle() {
            return `transform: translate(0, ${this.activeItem * 40}px);`;
        }
    },

    methods: {
        updateActiveItem() {
            this.$nextTick()
                .then(() => {
                    this.$children.forEach((item, i) => {
                        const linkIsActive = item.$children[0].$el.classList.contains('sw-side-navigation-item--active');
                        if (linkIsActive) this.activeItem = i;
                    });
                });
        }
    }
};
