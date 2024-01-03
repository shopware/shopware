import 'src/app/mixin/rule-container.mixin';
import { mount } from '@vue/test-utils';

const onAddPlaceholderMock = jest.fn();

async function createWrapper(propsData = {}) {
    return mount(
        {
            template: `
            <div class="sw-mock">
              <slot></slot>
            </div>
        `,
            mixins: [
                Shopware.Mixin.getByName('ruleContainer'),
            ],
            data() {
                return {
                    name: 'sw-mock-field',
                };
            },
            methods: {
                onAddPlaceholder() {
                    onAddPlaceholderMock();
                },
            },
        },
        {
            props: {
                condition: {},
                level: 0,
                ...propsData,
            },
            global: {
                stubs: {},
                mocks: {},
                provide: {
                    conditionDataProviderService: {},
                    createCondition: () => {
                    },
                    insertNodeIntoTree: () => {
                    },
                    removeNodeFromTree: () => {
                    },
                    childAssociationField: 'childAssociationField',
                },
                attachTo: document.body,
            },
        },
    );
}

describe('src/app/mixin/rule-container.mixin.ts', () => {
    let wrapper;

    beforeEach(async () => {
        onAddPlaceholderMock.mockClear();
        wrapper = await createWrapper();

        await flushPromises();
    });

    afterEach(async () => {
        if (wrapper) {
            await wrapper.unmount();
        }

        await flushPromises();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should compute the correct containerRowClass with even level', () => {
        expect(wrapper.vm.containerRowClass).toEqual({
            'container-condition-level__is--even': true,
            'is--disabled': false,
        });
    });

    it('should compute the correct containerRowClass with odd level', async () => {
        await wrapper.setProps({
            level: 1,
        });

        expect(wrapper.vm.containerRowClass).toEqual({
            'container-condition-level__is--odd': true,
            'is--disabled': false,
        });
    });

    it('should compute the correct containerRowClass with disabled row', async () => {
        await wrapper.setProps({
            disabled: true,
        });

        expect(wrapper.vm.containerRowClass).toEqual({
            'container-condition-level__is--even': true,
            'is--disabled': true,
        });
    });

    it('should compute the correct nextPosition', async () => {
        expect(wrapper.vm.nextPosition).toBe(0);
    });

    it('should compute the correct nextPosition with correct childAssociationField length', async () => {
        await wrapper.setProps({
            childAssociationField: 'childAssociationField',
            condition: {
                childAssociationField: [
                    {},
                    {},
                ],
            },
        });

        expect(wrapper.vm.nextPosition).toBe(2);
    });

    it('should call onAddPlaceholder when nextPosition changes to zero', async () => {
        expect(onAddPlaceholderMock).not.toHaveBeenCalled();

        await wrapper.setProps({
            childAssociationField: 'childAssociationField',
            condition: {
                childAssociationField: [
                    {},
                    {},
                ],
            },
        });

        expect(wrapper.vm.nextPosition).toBe(2);

        await wrapper.setProps({
            condition: {
                childAssociationField: [],
            },
        });

        expect(onAddPlaceholderMock).toHaveBeenCalled();
    });

    // TODO: Add more component tests
});
