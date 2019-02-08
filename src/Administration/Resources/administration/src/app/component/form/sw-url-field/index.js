import template from './sw-url-field.html.twig';

/**
 * @public
 * @description Url field component which supports a switch for https and http.
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-field type="url" label="Name" placeholder="Placeholder"
 * switchLabel="My shop uses https"></sw-field>
 */
export default {
    name: 'sw-url-field',
    extendsFrom: 'sw-text-field',
    template,

    props: {
        value: {
            type: String,
            default: ''
        },
        switchLabel: {
            type: String,
            required: true,
            default: ''
        }
    },

    data() {
        return {
            sslActive: true,
            urlInput: ''
        };
    },

    computed: {
        containPrefix() {
            return true;
        },

        prefixClass() {
            if (this.sslActive) {
                return 'is--ssl';
            }

            return '';
        },

        urlPrefix() {
            if (this.sslActive) {
                return 'https://';
            }

            return 'http://';
        },

        customPrefix() {
            return !!this.$scopedSlots.prefix || !!this.$slots.prefix || !!this.prefix;
        }
    },

    watch: {
        value: {
            immediate: true,
            handler(newUrl) {
                this.checkInput(newUrl);
            }
        }
    },

    methods: {
        urlChanged(inputValue) {
            const input = inputValue.target.value;
            if (input === null) {
                this.setUrlInputValue('');
                return;
            }

            this.checkInput(input);
        },

        checkInput(inputValue) {
            let newValue = inputValue;

            if (newValue.match(/^\s*https?:\/\//) !== null) {
                const sslFound = newValue.match(/^\s*https:\/\//);
                this.sslActive = (sslFound !== null);
                newValue = newValue.replace(/^\s*https?:\/\//, '');
            }

            this.setUrlInputValue(newValue);
        },

        /**
         * Set the urlInput variable and also the current value inside the html input.
         * The sw-field does not update the html if there is no change in the binding variable (urlInput /
         * because it gets watched), so it must be done manually (to replace / remove unwanted user input).
         *
         * @param newValue
         */
        setUrlInputValue(newValue) {
            this.urlInput = newValue;
            this.emitUrl();

            if (this.$refs.urlField !== undefined) {
                this.$refs.urlField.currentValue = this.urlInput;
            } else {
                this.$nextTick(() => {
                    this.$refs.urlField.currentValue = this.urlInput;
                });
            }
        },

        sslChanged(newValue) {
            this.sslActive = newValue;
            this.emitUrl();
        },

        emitUrl() {
            this.$emit('input', this.urlPrefix + this.urlInput.trim());
        }
    }


};
