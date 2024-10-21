/**
 * @package innovation
 */

import template from './sw-settings-media.html.twig';

const { Mixin } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: [
        'systemConfigApiService',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
            sliderValue: 0,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.isLoading = true;
            try {
                const values = await this.systemConfigApiService.getValues('core.media');
                this.sliderValue =
                    values['core.media.defaultLightIntensity'] !== undefined
                        ? values['core.media.defaultLightIntensity']
                        : 100;
            } catch (error) {
                if (error?.response?.data?.errors) {
                    this.createErrorNotification(error.response.data.errors);
                }
            } finally {
                this.isLoading = false;
            }
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            this.$refs.systemConfig
                .saveAll()
                .then(async () => {
                    this.isLoading = false;
                    this.isSaveSuccessful = true;

                    await this.systemConfigApiService.batchSave({
                        null: {
                            'core.media.defaultLightIntensity': this.sliderValue,
                        },
                    });
                })
                .catch((err) => {
                    this.isLoading = false;
                    this.createNotificationError({
                        message: err,
                    });
                });
        },

        onLoadingChanged(loading) {
            this.isLoading = loading;
        },

        onSliderChange(value) {
            this.sliderValue = value;
        },
    },
};
