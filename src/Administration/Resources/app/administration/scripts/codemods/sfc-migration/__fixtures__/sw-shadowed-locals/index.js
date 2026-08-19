import template from './sw-shadowed-locals.html.twig';

export default {
    template,

    props: {
        title: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            perPage: 25,
            currentPage: 1,
            iconSvgData: null,
            focussed: false,
            hidden: false,
            items: [],
        };
    },

    computed: {
        // A `const` declared after the reference still shadows it — reading it would be a temporal
        // dead zone error, not a fallback to the setup binding.
        displayedPages() {
            const currentPage = this.currentPage;

            return [currentPage];
        },

        // Only `props` can shadow a prop reference, so a local named after the prop is harmless.
        heading() {
            const title = 'fallback';

            return this.title || title;
        },
    },

    methods: {
        // The parameter shadows the data key: `perPage.value = …` would target the argument.
        onPageSizeChange(perPage) {
            this.perPage = Number(perPage);
        },

        // The arrow inherits the component `this` and its parameter shadows — this is the silent
        // case, because assigning `.value` on the resolved object throws nothing.
        loadIcon() {
            return Promise.resolve({}).then((iconSvgData) => {
                this.iconSvgData = iconSvgData.default;
            });
        },

        // The INSTANCE_PROPS replacement identifier is what has to be free here, not `$route`.
        currentLocation() {
            const route = { name: this.$route.name };

            return route;
        },

        setFocus(focussed) {
            this.focussed = focussed;
        },

        // A binding in a sibling nested function must not block the outer rewrite.
        countItems() {
            const total = [].map((items) => items.length);

            return this.items.length + total.length;
        },

        // Block scope is modelled: a binding the reference never reaches is not shadowing.
        toggle() {
            if (this.title) {
                const hidden = true;

                return hidden;
            }

            return this.hidden;
        },

        openModal(modalContent) {
            this.$refs.modalContent.focus();

            return modalContent;
        },
    },
};
