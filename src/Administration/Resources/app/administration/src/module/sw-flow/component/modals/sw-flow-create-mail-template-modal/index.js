import template from './sw-flow-create-mail-template-modal.html.twig';
import './sw-flow-create-mail-template-modal.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();
const utils = Shopware.Utils;

/**
 * @private
 * @package business-ops
 */
export default {
    template,

    inject: ['mailService', 'entityMappingService', 'repositoryFactory'],

    mixins: [
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            mailTemplate: {},
            mailTemplateType: {},
            editorConfig: {
                enableBasicAutocompletion: true,
            },
            isLoading: false,
        };
    },

    computed: {
        mailTemplateRepository() {
            return this.repositoryFactory.create('mail_template');
        },

        mailTemplateTypeRepository() {
            return this.repositoryFactory.create('mail_template_type');
        },

        mailTemplateCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('mailTemplateType');

            return criteria;
        },

        outerCompleterFunction() {
            return (function completerWrapper(entityMappingService, innerMailTemplateType) {
                function completerFunction(prefix) {
                    const properties = [];
                    Object.keys(
                        entityMappingService.getEntityMapping(prefix, innerMailTemplateType.availableEntities),
                    ).forEach((val) => {
                        properties.push({
                            value: val,
                        });
                    });
                    return properties;
                }
                return completerFunction;
            }(this.entityMappingService, this.mailTemplateType));
        },

        ...mapPropertyErrors('mailTemplate', [
            'mailTemplateTypeId',
            'subject',
        ]),
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!Shopware.State.getters['context/isSystemDefaultLanguage']) {
                Shopware.State.commit('context/resetLanguageToDefault');
            }

            this.mailTemplate = this.mailTemplateRepository.create(Shopware.Context.api, utils.createId());
        },

        onClose() {
            this.$emit('modal-close');
        },

        onAddMailTemplate() {
            const mailTemplateSubject = this.mailTemplate.subject || this.placeholder(this.mailTemplate, 'subject');

            this.isSaveSuccessful = false;
            this.isLoading = true;

            return this.mailTemplateRepository.save(this.mailTemplate).then(() => {
                this.getMailTemplate();
            }).catch(error => {
                let errorMsg = '';
                this.isLoading = false;

                if (error.response.data.errors.length > 0) {
                    const errorDetailMsg = error.response.data.errors[0].detail;
                    errorMsg = `<br/> ${this.$tc('sw-mail-template.detail.textErrorMessage')}: "${errorDetailMsg}"`;
                }

                this.createNotificationError({
                    message: this.$tc(
                        'sw-mail-template.detail.messageSaveError',
                        0,
                        { subject: mailTemplateSubject },
                    ) + errorMsg,
                });
            });
        },

        getMailTemplateType() {
            if (!this.mailTemplate?.mailTemplateTypeId) {
                return Promise.resolve();
            }

            return this.mailTemplateTypeRepository.get(this.mailTemplate.mailTemplateTypeId).then((item) => {
                this.mailTemplateType = item;
                this.$refs.htmlEditor.defineAutocompletion(this.outerCompleterFunction);
                this.$refs.plainEditor.defineAutocompletion(this.outerCompleterFunction);
            });
        },

        async onChangeType(id) {
            if (!id) {
                return;
            }

            try {
                await this.getMailTemplateType();
            } catch (e) {
                let errorMsg = '';
                if (e.response.data.errors.length > 0) {
                    const errorDetailMsg = e.response.data.errors[0].detail;
                    errorMsg = `<br/> ${this.$tc('sw-mail-template.detail.textErrorMessage')}: "${errorDetailMsg}"`;
                }

                this.createNotificationError({
                    message: errorMsg,
                });
            }
        },

        getMailTemplate() {
            return this.mailTemplateRepository.get(this.mailTemplate.id, Shopware.Context.api, this.mailTemplateCriteria)
                .then((data) => {
                    this.$emit('process-finish', data);
                }).catch(() => {
                    this.$emit('process-finish', null);
                }).finally(() => {
                    this.isLoading = false;
                    this.onClose();
                });
        },
    },
};
