import { shallowMount, createLocalVue, Wrapper } from '@vue/test-utils';
import VueRouter from 'vue-router';
import 'src/module/sw-product/component/sw-product-feature-set-form';
import 'src/app/component/base/sw-container';
import 'src/app/component/utils/sw-inherit-wrapper';
import 'src/app/component/form/select/entity/sw-entity-single-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';

// TODO: Remove skip from tests when FEATURE_NEXT_6997 flag is removed

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
        singleSelectSelection: 'sw-entity-single-select__selection'
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

    const productMock = (additionalProperties) => ({ featureSet: featureSetMock, ...additionalProperties });

    const featureSetFormComponent = () => {
        const localVue = createLocalVue();

        localVue.use(VueRouter);
        localVue.directive('tooltip', {});

        return shallowMount(Shopware.Component.build('sw-product-feature-set-form'), {
            localVue,
            stubs: {
                'sw-container': Shopware.Component.build('sw-container'),
                'sw-inherit-wrapper': Shopware.Component.build('sw-inherit-wrapper'),
                'sw-inheritance-switch': true,
                'sw-icon': true,
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
            mocks: {
                $tc: (translationPath) => translationPath,
                $device: {
                    onResize: () => {}
                }
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
                parentProduct: () => productMock({ featureSetId: featureSetMock.id }),
                loading: () => {}
            }
        });
    };

    /*
     * Workaround, since the current vue-test-utils version doesn't support get()
     *
     * @see https://vue-test-utils.vuejs.org/api/wrapper/#get
     */
    const findSecure = (wrapperEl, findArg) => {
        const el = wrapperEl.find(findArg);

        if (el instanceof Wrapper) {
            return el;
        }

        throw new Error(`Could not find element ${findArg}.`);
    };

    beforeEach(() => {
        wrapper = featureSetFormComponent();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it.skip('should be able to instantiate', () => {
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it.skip('has the correct class', () => {
        expect(wrapper.classes()).toContain(classes.componentRoot);
    });

    it.skip('contains the description container', () => {
        expect(findSecure(wrapper, `.${classes.descriptionContainer}`).exists()).toBeTruthy();
    });

    it.skip('has a complete description', () => {
        const descriptionContainer = findSecure(wrapper, `.${classes.descriptionContainer}`);

        const title = findSecure(descriptionContainer, `.${classes.descriptionTitle}`);
        const description = findSecure(descriptionContainer, `.${classes.descriptionBody}`);
        const configInformation = findSecure(descriptionContainer, `.${classes.descriptionConfigInformation}`);

        expect(title.exists()).toBeTruthy();

        expect(description.exists()).toBeTruthy();
        expect(description.text()).toEqual(text.descriptionBody);

        expect(configInformation.exists()).toBeTruthy();
        expect(configInformation.attributes().path).toEqual(text.descriptionConfigInformation);
    });

    it.skip('has a link to the feature set config module', () => {
        const linkContainer = findSecure(wrapper, `.${classes.descriptionLink}`);
        const link = findSecure(linkContainer, `.${classes.quickLink}`);

        expect(link.exists()).toBeTruthy();
        expect(link.text()).toEqual(text.descriptionLink);
        expect(link.props().to.name).toEqual(text.descriptionLinkTarget);
    });

    it.skip('contains the form container', () => {
        expect(findSecure(wrapper, `.${classes.formContainer}`).exists()).toBeTruthy();
    });

    it.skip('has a sw-entity-single-select for selecting templates and supports inheritance', () => {
        const form = findSecure(wrapper, `.${classes.formContainer}`);

        const inheritWrapper = findSecure(form, `.${classes.formInheritWrapper}`);
        const singleSelect = findSecure(inheritWrapper, `.${classes.templateSingleSelect}`);

        expect(inheritWrapper.props().label).toEqual(text.templateSelectLabel);
        expect(singleSelect.props().placeholder).toEqual(text.templateSelectPlaceholder);
    });

    it.skip('shows the current product\'s featureSet', () => {
        const singleSelect = findSecure(wrapper, `.${classes.templateSingleSelect}`);
        const selection = findSecure(singleSelect, `.${classes.singleSelectSelection}`);

        expect(selection.text()).toEqual(featureSetMock.name);
    });
});
