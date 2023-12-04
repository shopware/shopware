<script>
import Vue from 'vue';

export default {
    name: 'static-renderer',
    props: {
        example: {
            type: String,
            required: true
        }
    },

    data() {
        return {
            templateRender: null
        };
    },

    watch: {
        example: {
            immediate: true,
            handler() {
                const res = Vue.compile(this.example);
                this.templateRender = res.render;
                this.$options.staticRenderFns = [];
                this._staticTrees = [];

                for (let i in res.staticRenderFns) {
                    this.$options.staticRenderFns.push(res.staticRenderFns[i])
                }
            }
        }
    },

    render(h) {
        if (!this.templateRender) {
            return h('div', 'Loading example...');
        }

        return this.templateRender();
    }
}
</script>
