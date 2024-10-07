import { mount } from '@vue/test-utils';

async function createWrapper({ permissions, modalTitle, selectedEntity }) {
    return mount(
        await wrapTestComponent('sw-extension-permissions-details-modal', {
            sync: true,
        }),
        {
            global: {
                mocks: {
                    $tc: (...args) => (args.length === 1 ? args[0] : JSON.stringify(...args)),
                    $te: () => true,
                },
                stubs: {
                    'sw-modal': {
                        props: ['title'],
                        // eslint-disable-next-line max-len
                        template:
                            '<div><div class="sw-modal__title">{{ title }}</div><div class="sw-modal__body"><slot/></div><slot name="modal-footer"></slot></div>',
                    },
                    'sw-icon': {
                        props: [
                            'name',
                            'color',
                        ],
                        template: '<div class="icon">name:{{ name }} color:{{ color }}</div>',
                    },
                    'sw-button': true,
                },
            },
            props: {
                permissions,
                modalTitle,
                selectedEntity,
            },
        },
    );
}

/**
 * @package checkout
 */
describe('sw-extension-permissions-details-modal', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper({
            modalTitle: 'Sample Extension Label',
            selectedEntity: 'product',
            permissions: {
                product: {
                    product: [
                        'create',
                        'read',
                    ],
                    product_visibility: [
                        'create',
                        'read',
                    ],
                },
                promotion: {
                    promotion: [
                        'create',
                        'read',
                    ],
                },
            },
        });

        expect(wrapper.vm).toBeTruthy();
    });

    it('should display the permissions for creating and reading', async () => {
        const wrapper = await createWrapper({
            modalTitle: 'Sample Extension Label',
            selectedEntity: 'product',
            permissions: {
                product: {
                    product: [
                        'create',
                        'read',
                    ],
                    product_visibility: [
                        'create',
                        'read',
                    ],
                },
                promotion: {
                    promotion: [
                        'create',
                        'read',
                    ],
                },
            },
        });

        expect(wrapper.find('.sw-modal__title').text()).toBe('Sample Extension Label');

        const thead = wrapper.findAll('.sw-extension-permissions-details-modal__operation-header');

        expect(thead.at(0).text()).toBe('sw-extension-store.component.sw-extension-permissions-details-modal.operationRead');
        expect(thead.at(1).text()).toBe(
            'sw-extension-store.component.sw-extension-permissions-details-modal.operationUpdate',
        );
        expect(thead.at(2).text()).toBe(
            'sw-extension-store.component.sw-extension-permissions-details-modal.operationCreate',
        );
        expect(thead.at(3).text()).toBe(
            'sw-extension-store.component.sw-extension-permissions-details-modal.operationDelete',
        );

        const categoryHeader = wrapper.findAll('.sw-extension-permissions-details-modal__category');

        expect(categoryHeader.at(0).text()).toBe('entityCategories.product.title');
        expect(categoryHeader.at(1).text()).toBe('entityCategories.promotion.title');

        const entityLabels = wrapper.findAll('.sw-extension-permissions-details-modal__entity-label');
        expect(entityLabels).toHaveLength(3);

        expect(entityLabels.at(0).text()).toBe('entityCategories.product.entities.product');
        expect(entityLabels.at(1).text()).toBe('entityCategories.product.entities.product_visibility');
        expect(entityLabels.at(2).text()).toBe('entityCategories.promotion.entities.promotion');

        const allIcons = wrapper
            .findAll('.sw-extension-permissions-details-modal__operation')
            .map((td) => td.find('.icon').text());

        expect(allIcons).toStrictEqual([
            'name:regular-checkmark-xs color:#37D046',
            'name:regular-times-s color:#DE294C',
            'name:regular-checkmark-xs color:#37D046',
            'name:regular-times-s color:#DE294C',
            'name:regular-checkmark-xs color:#37D046',
            'name:regular-times-s color:#DE294C',
            'name:regular-checkmark-xs color:#37D046',
            'name:regular-times-s color:#DE294C',
            'name:regular-checkmark-xs color:#37D046',
            'name:regular-times-s color:#DE294C',
            'name:regular-checkmark-xs color:#37D046',
            'name:regular-times-s color:#DE294C',
        ]);
    });

    it('should display the permissions for all product permissions', async () => {
        const wrapper = await createWrapper({
            modalTitle: 'Sample Extension Label',
            selectedEntity: 'product',
            permissions: {
                product: {
                    product: [
                        'create',
                        'read',
                        'update',
                        'delete',
                    ],
                    product_visibility: ['create'],
                },
                promotion: {
                    promotion: ['create'],
                    promotion_individual_code: [
                        'create',
                        'read',
                        'update',
                        'delete',
                    ],
                },
            },
        });

        expect(wrapper.find('.sw-modal__title').text()).toBe('Sample Extension Label');

        const thead = wrapper.findAll('.sw-extension-permissions-details-modal__operation-header');

        expect(thead.at(0).text()).toBe('sw-extension-store.component.sw-extension-permissions-details-modal.operationRead');
        expect(thead.at(1).text()).toBe(
            'sw-extension-store.component.sw-extension-permissions-details-modal.operationUpdate',
        );
        expect(thead.at(2).text()).toBe(
            'sw-extension-store.component.sw-extension-permissions-details-modal.operationCreate',
        );
        expect(thead.at(3).text()).toBe(
            'sw-extension-store.component.sw-extension-permissions-details-modal.operationDelete',
        );

        const categoryHeader = wrapper.findAll('.sw-extension-permissions-details-modal__category');

        expect(categoryHeader.at(0).text()).toBe('entityCategories.product.title');
        expect(categoryHeader.at(1).text()).toBe('entityCategories.promotion.title');

        const entityLabels = wrapper.findAll('.sw-extension-permissions-details-modal__entity-label');
        expect(entityLabels).toHaveLength(4);

        expect(entityLabels.at(0).text()).toBe('entityCategories.product.entities.product');
        expect(entityLabels.at(1).text()).toBe('entityCategories.product.entities.product_visibility');
        expect(entityLabels.at(2).text()).toBe('entityCategories.promotion.entities.promotion');
        expect(entityLabels.at(3).text()).toBe('entityCategories.promotion.entities.promotion_individual_code');

        const allIcons = wrapper
            .findAll('.sw-extension-permissions-details-modal__operation')
            .map((td) => td.find('.icon').text());

        expect(allIcons).toStrictEqual([
            'name:regular-checkmark-xs color:#37D046',
            'name:regular-checkmark-xs color:#37D046',
            'name:regular-checkmark-xs color:#37D046',
            'name:regular-checkmark-xs color:#37D046',
            'name:regular-times-s color:#DE294C',
            'name:regular-times-s color:#DE294C',
            'name:regular-checkmark-xs color:#37D046',
            'name:regular-times-s color:#DE294C',
            'name:regular-times-s color:#DE294C',
            'name:regular-times-s color:#DE294C',
            'name:regular-checkmark-xs color:#37D046',
            'name:regular-times-s color:#DE294C',
            'name:regular-checkmark-xs color:#37D046',
            'name:regular-checkmark-xs color:#37D046',
            'name:regular-checkmark-xs color:#37D046',
            'name:regular-checkmark-xs color:#37D046',
        ]);
    });
});
