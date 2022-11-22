import template from './sw-cms-el-config-form.html.twig';
import './sw-cms-el-config-form.scss';

const { Mixin } = Shopware;

/**
 * @private
 * @package content
 */
export default {
    template,

    inject: ['systemConfigApiService'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        getLastMailClass() {
            if (this.element.config.mailReceiver.value.length === 1) {
                return 'is--last';
            }
            return '';
        },
    },

    created() {
        this.createdComponent();
        this.setShopMail();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('form');
        },

        getShopMail() {
            return new Promise(resolve => {
                this.systemConfigApiService
                    .getValues('core.basicInformation')
                    .then(response => {
                        resolve(response['core.basicInformation.email']);
                    });
            });
        },

        setShopMail() {
            this.getShopMail().then(shopMail => {
                if (this.element.config.defaultMailReceiver.value
                    && !this.element.config.mailReceiver.value.includes(shopMail)) {
                    this.element.config.mailReceiver.value.push(shopMail);
                }
            });
        },

        updateMailReceiver() {
            if (this.validateMail()) {
                this.getShopMail().then(shopMail => {
                    if (this.element.config.mailReceiver.value.includes(shopMail)) {
                        this.element.config.defaultMailReceiver.value = true;
                    } else {
                        this.element.config.defaultMailReceiver.value = false;
                    }
                });
            }
        },

        validateMail() {
            const lastMail = this.element.config.mailReceiver.value[this.element.config.mailReceiver.value.length - 1];
            if (lastMail) {
                const mailformat = /^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/;

                if (lastMail.match(mailformat) == null) {
                    this.element.config.mailReceiver.value.pop();
                    return false;
                }
            }
            return true;
        },
    },
};
