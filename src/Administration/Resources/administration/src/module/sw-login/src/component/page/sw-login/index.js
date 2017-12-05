import template from './sw-login.html.twig';
import './sw-login.less';

Shopware.Component.register('sw-login', {
    inject: ['loginService'],

    data() {
        return {
            isWorking: false,
            error: '',
            message: '',
            username: '',
            password: ''
        };
    },

    methods: {
        onSubmit() {
            const loginService = this.loginService;

            // Reset error message
            this.error = '';
            this.message = '';

            this.isWorking = true;

            // Login user and set the token
            loginService.loginByUsername(this.username, this.password).then((response) => {
                loginService.setBearerAuthentication(response.data.token, response.data.expiry);

                this.isWorking = false;
                this.$router.push({
                    name: 'core'
                });
            }).catch((err) => {
                let data = err.response.data.errors;
                data = data.length > 1 ? data : data[0];

                // Set error message
                this.error = data.title;
                this.message = data.detail;

                this.isWorking = false;
            });
        }
    },

    template
});
