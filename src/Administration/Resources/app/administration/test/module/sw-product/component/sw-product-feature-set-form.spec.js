import { shallowMount, createLocalVue, Wrapper } from '@vue/test-utils';
import VueRouter from 'vue-router';
import 'src/module/sw-product/component/sw-product-feature-set-form';
import 'src/app/component/base/sw-container';
import 'src/app/component/utils/sw-inherit-wrapper';
import 'src/app/component/form/select/entity/sw-entity-multi-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/select/base/sw-select-selection-list';

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
        templateMultiSelect: 'sw-entity-multi-select',
        multiSelectList: 'sw-select-selection-list',
        multiSelectItem: 'sw-select-selection-list__item'
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
        name: 'TestSet',
        description: 'Lorem ipsum dolor sit amet',
        features: [
            {
                id: 'referencePrice',
                type: 'product',
                position: 1
            }
        ]
    };

    const productMock = (additionalProperties) => ({ featureSets: [featureSetMock], ...additionalProperties });

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
                'sw-entity-multi-select': Shopware.Component.build('sw-entity-multi-select'),
                'sw-select-base': Shopware.Component.build('sw-select-base'),
                'sw-block-field': Shopware.Component.build('sw-block-field'),
                'sw-base-field': Shopware.Component.build('sw-base-field'),
                'sw-select-selection-list': Shopware.Component.build('sw-select-selection-list'),
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
                    create: () => {},
                    search: () => {}
                }
            },
            computed: {
                product: productMock,
                parentProduct: productMock,
                loading: productMock
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

    it.skip('has a sw-entity-multi-select for selecting templates and supports inheritance', () => {
        const form = findSecure(wrapper, `.${classes.formContainer}`);
        const inheritWrapper = findSecure(form, `.${classes.formInheritWrapper}`);
        const multiSelect = findSecure(inheritWrapper, `.${classes.templateMultiSelect}`);

        expect(inheritWrapper.props().label).toEqual(text.templateSelectLabel);
        expect(multiSelect.props().placeholder).toEqual(text.templateSelectPlaceholder);
    });

    it.skip('shows the current product\'s featureSets', () => {
        const multiSelect = findSecure(wrapper, `.${classes.templateMultiSelect}`);
        const selectionList = findSecure(multiSelect, `.${classes.multiSelectList}`);

        expect(selectionList.contains(`.${classes.multiSelectItem}`)).toBeTruthy();

        const item = findSecure(selectionList, `.${classes.multiSelectItem}`);

        expect(item.text()).toEqual(featureSetMock.name);
    });
});
