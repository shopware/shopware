import utils from 'src/core/service/util.service';
import template from './sw-login.html.twig';

Shopware.Component.register('sw-login', {
    inject: ['loginService', 'applicationState'],

    data() {
        return {
            error: '',
            message: ''
        };
    },

    computed: {
        state() {
            return this.applicationState.mapState([
                'user'
            ]);
        }
    },

    methods: {
        onHideErrorMessage() {
            this.error = '';
            this.message = '';
        },

        onLogin() {
            const formData = new FormData(this.$refs.form.$el.querySelector('form'));
            const data = utils.formDataToObject(formData);

            this.error = '';
            this.message = '';

            this.loginService
                .loginByUsername(data.username, data.password)
                .then((response) => {
                    response = response.data;

                    if (!response.success) {
                        this.error = response.error;
                        this.message = response.message;
                        return false;
                    }

                    this.applicationState.commit('setUser', response.user);
                    this.applicationState.commit('setToken', response.token);

                    this.$router.push({ path: '/' });
                    return true;
                }).catch((err) => {
                    this.error = err.message;
                });
        }
    },

    template
});
