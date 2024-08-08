/*
 * @package inventory
 * @group disabledCompat
 */

import { mount, config } from '@vue/test-utils';
import { createRouter, createWebHashHistory } from 'vue-router';
import { reactive } from 'vue';

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
        inheritanceSwitch: 'sw-inheritance-switch',
    };

    const text = {
        descriptionTitle: 'sw-product.featureSets.descriptionTitle',
        descriptionBody: 'sw-product.featureSets.descriptionBody',
        descriptionConfigInformation: 'sw-product.featureSets.configInformation',
        descriptionLink: 'sw-product.featureSets.linkFeatureSetsConfig',
        descriptionLinkTarget: 'sw.settings.product.feature.sets.index',
        templateSelectLabel: 'sw-product.featureSets.templateSelectFieldLabel',
        templateSelectPlaceholder: 'sw-product.featureSets.templateSelectFieldPlaceholder',
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
                position: 1,
            },
        ],
    };

    const productMock = (additionalProperties) => {
        return reactive({
            featureSet: featureSetMock,
            ...additionalProperties,
        });
    };

    async function createWrapper() {
        // delete global $router and $routes mocks
        delete config.global.mocks.$router;
        delete config.global.mocks.$route;

        const router = createRouter({
            routes: [
                {
                    name: 'sw.settings.product.feature.sets.index',
                    params: {},
                    component: {},
                },
            ],
            history: createWebHashHistory(),
        });

        return mount(await wrapTestComponent('sw-product-feature-set-form', { sync: true }), {
            global: {
                plugins: [router],
                stubs: {
                    'sw-container': await wrapTestComponent('sw-container'),
                    'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper'),
                    'sw-inheritance-switch': await wrapTestComponent('sw-inheritance-switch'),
                    'sw-icon': {
                        template: '<div class="sw-icon" @click="$emit(\'click\')"></div>',
                    },
                    'sw-icons-custom-inherited': true,
                    'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select'),
                    'sw-loader': true,
                    'sw-select-base': await wrapTestComponent('sw-select-base'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-field-error': true,
                    'sw-label': true,
                    i18n: {
                        template: '<div class="i18n-stub"><slot></slot></div>',
                    },
                    'sw-help-text': true,
                    'sw-product-variant-info': true,
                    'sw-highlight-text': true,
                    'sw-select-result': true,
                    'sw-select-result-list': true,
                    'sw-ai-copilot-badge': true,
                },
                provide: {
                    repositoryFactory: {
                        create() {
                            return {
                                get() {
                                    return new Promise((resolve) => {
                                        resolve(featureSetMock);
                                    });
                                },
                            };
                        },
                        search() {
                            return {};
                        },
                    },
                },
            },
            computed: {
                product: () => productMock({ featureSetId: featureSetMock.id }),
                parentProduct: () => productMock({ featureSetId: featureSetMock.id, id: 'a12b3c' }),
                loading: () => {
                },
            },
        });
    }

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
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
        const link = wrapper.getComponent({ name: 'router-link' });

        expect(link.exists()).toBe(true);
        expect(link.text()).toEqual(text.descriptionLink);
        expect(link.props().to.name).toEqual(text.descriptionLinkTarget);
    });

    it('contains the form container', async () => {
        expect(wrapper.find(`.${classes.formContainer}`).exists()).toBe(true);
    });

    it('has a sw-entity-single-select for selecting templates and supports inheritance', async () => {
        const form = wrapper.get(`.${classes.formContainer}`);

        const inheritWrapper = form.getComponent({ name: 'sw-inherit-wrapper__wrapped' });
        const singleSelect = inheritWrapper.getComponent({ name: 'sw-entity-single-select__wrapped' });

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
