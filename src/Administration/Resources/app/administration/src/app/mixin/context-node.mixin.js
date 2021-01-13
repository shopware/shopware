Shopware.Mixin.register('contextNodeMixin', {
    provide() {
        return {
            registerAtParent: this.registerChild,
            removeFromParent: this.removeChild
        };
    },

    data() {
        return {
            childNodes: []
        };
    },

    methods: {
        registerChild(child) {
            this.childNodes.push(child);
            return this;
        },
        removeChild(child) {
            this.childNodes = this.childNodes.filter((c) => c !== child);
        }
    }
});
