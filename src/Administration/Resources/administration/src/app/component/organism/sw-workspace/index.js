import template from 'src/app/component/organism/sw-workspace/sw-workspace.html.twig';
import 'src/app/component/organism/sw-workspace/sw-workspace.less';

export default Shopware.Component.register('sw-workspace', {
    template,
    computed: {
        iconClassName() {
            if (!this.icon) {
                return 'icon--empty';
            }

            return `icon-${this.icon}`;
        }
    },

    data() {
        return {
            title: '',
            icon: '',
            primaryColor: '',
            parentRoute: ''
        };
    },

    props: ['name'],

    created() {
        this.setupWorkspace();
    },

    updated() {
        this.setupWorkspace();
    },

    methods: {
        setupWorkspace() {
            const module = this.$route.meta.$module;

            if (!module) {
                return;
            }

            this.icon = module.icon;
            this.title = module.name;
            this.primaryColor = module.color;

            if (this.$route.meta.parentPath) {
                this.parentRoute = this.$route.meta.parentPath;
            }
        }
    }
});
