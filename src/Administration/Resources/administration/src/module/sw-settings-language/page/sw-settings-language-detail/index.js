import template from './sw-settings-language-detail.html.twig';
import './sw-settings-language-detail.scss';

const { Component, State, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-settings-language-detail', {
    template,

    inject: ['repositoryFactory', 'apiContext'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder')
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel'
    },

    props: {
        languageId: {
            type: String,
            required: false,
            default: null
        }
    },

    data() {
        return {
            language: null,
            usedLocales: [],
            showAlertForChangeParentLanguage: false,
            isLoading: false,
            isSaveSuccessful: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.languageHasName ? this.language.name : '';
        },

        languageRepository() {
            return this.repositoryFactory.create('language');
        },

        languageStore() {
            return State.getStore('language');
        },

        isIsoCodeRequired() {
            return !this.language.parentId;
        },

        languageHasName() {
            return this.language !== null && this.language.name;
        },

        isNewLanguage() {
            return this.language && this.language.isNew();
        },

        usedLocaleCriteria() {
            return (new Criteria(1, 1)).addAggregation(
                Criteria.terms('usedLocales', 'language.locale.code', null, null, null)
            );
        },

        tooltipSave() {
            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light'
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light'
            };
        },

        parentLanguageCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.not('and', [Criteria.equals('id', this.language.id)]));
            return criteria;
        }
    },

    watch: {
        languageId() {
            // We must reset the page if the user clicks his browsers back button and navigates back to create
            if (this.languageId === null) {
                this.createdComponent();
            }
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.languageId) {
                this.languageStore.setCurrentId(this.languageStore.systemLanguageId);
                this.language = this.languageRepository.create(this.apiContext);
            } else {
                this.loadEntityData();
            }

            this.languageRepository.search(
                this.usedLocaleCriteria,
                this.apiContext
            ).then(({ aggregations }) => {
                this.usedLocales = aggregations.usedLocales.buckets;
            });
        },

        loadEntityData() {
            this.isLoading = true;
            this.languageRepository.get(this.languageId, this.apiContext).then((language) => {
                this.isLoading = false;
                this.language = language;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        onInputLanguage() {
            const origin = this.language.getOrigin();
            if (this.language.isNew() || !origin.parentId) {
                return;
            }

            this.showAlertForChangeParentLanguage = origin.parentId !== this.language.parentId;
        },

        isLocaleAlreadyUsed(item) {
            const usedByAnotherLanguage = this.usedLocales.some((locale) => {
                return item.code === locale.key;
            });

            if (usedByAnotherLanguage) {
                return true;
            }

            if (!this.language.locale) {
                return false;
            }

            return item.code === this.language.locale.code;
        },

        onSave() {
            this.isLoading = true;
            this.languageRepository.save(this.language, this.apiContext).then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
                if (!this.languageId) {
                    this.$router.push({ name: 'sw.settings.language.detail', params: { id: this.language.id } });
                }
            }).then(() => {
                this.loadEntityData();
            });
        },

        onCancel() {
            this.$router.push({ name: 'sw.settings.language.index' });
        },

        onChangeLanguage() {
            this.loadEntityData();
        }
    }
});
