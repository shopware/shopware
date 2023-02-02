import { shallowMount, createLocalVue, config } from '@vue/test-utils';
import VueRouter from 'vue-router';
import 'src/module/sw-product/component/sw-product-feature-set-form';
import 'src/app/component/base/sw-container';
import 'src/app/component/utils/sw-inherit-wrapper';
import 'src/app/component/form/select/entity/sw-entity-single-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/base/sw-inheritance-switch';
import Vue from 'vue';

describe('src/module/sw-product/component/sw-product-feature-set-form', () => {
    let wrapper;

    const classes = {
        componentRoot: 'sw-product-feature-set-form__container',
        descriptionContainer: 'sw-product-feature-set-form__description',
        descriptionTitle: 'sw-product-feature-set-form__description-title',
        descriptionBody: 'sw-product-feature-set-form__description-body',
        descriptionConfigInformation: 'sw-product-feature-set-form__description-config-info',
        descriptionLink: 'sw-product-feature-set-form__description-link',
        quickLink: 'sw-card__quick-link',
        formContainer: 'sw-product-feature-set-form__form',
        formInheritWrapper: 'sw-inherit-wrapper',
        templateSingleSelect: 'sw-entity-single-select',
        singleSelectSelection: 'sw-entity-single-select__selection',
        inheritanceSwitch: 'sw-inheritance-switch'
    };

    const text = {
        descriptionTitle: 'sw-product.featureSets.descriptionTitle',
        descriptionBody: 'sw-product.featureSets.descriptionBody',
        descriptionConfigInformation: 'sw-product.featureSets.configInformation',
        descriptionLink: 'sw-product.featureSets.linkFeatureSetsConfig',
        descriptionLinkTarget: 'sw.settings.product.feature.sets.index',
        templateSelectLabel: 'sw-product.featureSets.templateSelectFieldLabel',
        templateSelectPlaceholder: 'sw-product.featureSets.templateSelectFieldPlaceholder'
    };

    const featureSetMock = {
        id: '783397afdd914495a4391666f1a4ab72',
        name: 'TestSet',
        description: 'Lorem ipsum dolor sit amet',
        features: [
            {
                id: null,
                name: null,
                type: 'referencePrice',
                position: 1
            }
        ]
    };

    const productMock = (additionalProperties) => {
        return Vue.observable({
            featureSet: featureSetMock,
            ...additionalProperties
        });
    };

    function createWrapper() {
        // delete global $router and $routes mocks
        delete config.mocks.$router;
        delete config.mocks.$route;

        const localVue = createLocalVue();

        localVue.use(VueRouter);

        return shallowMount(Shopware.Component.build('sw-product-feature-set-form'), {
            localVue,
            stubs: {
                'sw-container': Shopware.Component.build('sw-container'),
                'sw-inherit-wrapper': Shopware.Component.build('sw-inherit-wrapper'),
                'sw-inheritance-switch': Shopware.Component.build('sw-inheritance-switch'),
                'sw-icon': {
                    template: '<div class="sw-icon" @click="$emit(\'click\')"></div>'
                },
                'sw-icons-custom-inherited': true,
                'sw-entity-single-select': Shopware.Component.build('sw-entity-single-select'),
                'sw-loader': true,
                'sw-select-base': Shopware.Component.build('sw-select-base'),
                'sw-block-field': Shopware.Component.build('sw-block-field'),
                'sw-base-field': Shopware.Component.build('sw-base-field'),
                'sw-field-error': true,
                'sw-label': true,
                i18n: true
            },
            provide: {
                repositoryFactory: {
                    create() {
                        return {
                            get() {
                                return new Promise((resolve) => {
                                    return resolve(featureSetMock);
                                });
                            }
                        };
                    },
                    search() {
                        return {};
                    }
                }
            },
            computed: {
                product: () => productMock({ featureSetId: featureSetMock.id }),
                parentProduct: () => productMock({ featureSetId: featureSetMock.id, id: 'a12b3c' }),
                loading: () => {}
            }
        });
    }

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be able to instantiate', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('has the correct class', async () => {
        expect(wrapper.classes()).toContain(classes.componentRoot);
    });

    it('contains the description container', async () => {
        expect(wrapper.find(`.${classes.descriptionContainer}`).exists()).toBe(true);
    });

    it('has a complete description', async () => {
        const descriptionContainer = wrapper.get(`.${classes.descriptionContainer}`);

        // checks if the descriptionTitle exists
        descriptionContainer.get(`.${classes.descriptionTitle}`);

        const description = descriptionContainer.get(`.${classes.descriptionBody}`);
        const configInformation = descriptionContainer.get(`.${classes.descriptionConfigInformation}`);


        expect(description.text()).toEqual(text.descriptionBody);

        expect(configInformation.attributes().path).toEqual(text.descriptionConfigInformation);
    });

    it('has a link to the feature set config module', async () => {
        const linkContainer = wrapper.get(`.${classes.descriptionLink}`);
        const link = linkContainer.get(`.${classes.quickLink}`);

        expect(link.exists()).toBe(true);
        expect(link.text()).toEqual(text.descriptionLink);
        expect(link.props().to.name).toEqual(text.descriptionLinkTarget);
    });

    it('contains the form container', async () => {
        expect(wrapper.find(`.${classes.formContainer}`).exists()).toBe(true);
    });

    it('has a sw-entity-single-select for selecting templates and supports inheritance', async () => {
        const form = wrapper.get(`.${classes.formContainer}`);

        const inheritWrapper = form.get(`.${classes.formInheritWrapper}`);
        const singleSelect = inheritWrapper.get(`.${classes.templateSingleSelect}`);

        expect(inheritWrapper.props().label).toEqual(text.templateSelectLabel);
        expect(singleSelect.props().placeholder).toEqual(text.templateSelectPlaceholder);
    });

    it('shows the current product\'s featureSet', async () => {
        const singleSelect = wrapper.get(`.${classes.templateSingleSelect}`);
        const selection = singleSelect.get(`.${classes.singleSelectSelection}`);

        expect(selection.text()).toEqual(featureSetMock.name);
    });

    it('show not the inherit value', async () => {
        const inheritanceSwitch = wrapper.get(`.${classes.inheritanceSwitch}`);

        expect(inheritanceSwitch.classes()).toContain('sw-inheritance-switch--is-not-inherited');
    });

    it('show switch to inherit value', async () => {
        const inheritanceSwitch = wrapper.get(`.${classes.inheritanceSwitch}`);

        await inheritanceSwitch.find('.sw-icon').trigger('click');

        expect(inheritanceSwitch.classes()).toContain('sw-inheritance-switch--is-inherited');
    });

    it('show remove inheritance', async () => {
        const inheritanceSwitch = wrapper.get(`.${classes.inheritanceSwitch}`);

        await inheritanceSwitch.find('.sw-icon').trigger('click');

        expect(inheritanceSwitch.classes()).toContain('sw-inheritance-switch--is-inherited');

        await inheritanceSwitch.find('.sw-icon').trigger('click');

        expect(inheritanceSwitch.classes()).toContain('sw-inheritance-switch--is-not-inherited');
    });
});
