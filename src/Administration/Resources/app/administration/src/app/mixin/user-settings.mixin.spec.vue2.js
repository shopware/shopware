import 'src/app/mixin/user-settings.mixin';
import { shallowMount } from '@vue/test-utils_v2';

let createRepositoryFactoryMock;

async function createWrapper() {
    return shallowMount({
        template: `
            <div class="sw-mock">
              <slot></slot>
            </div>
        `,
        mixins: [
            Shopware.Mixin.getByName('user-settings'),
        ],
        data() {
            return {
                name: 'sw-mock-field',
            };
        },
    }, {
        stubs: {},
        mocks: {
            repositoryFactory: {
                create: () => createRepositoryFactoryMock,
            },
        },
        propsData: {},
        provide: {},
        attachTo: document.body,
    });
}

describe('src/app/mixin/user-settings.mixin.ts', () => {
    let wrapper;

    beforeEach(async () => {
        createRepositoryFactoryMock = undefined;
        global.activeAclRoles = [
            'user_config:read',
            'user_config:create',
            'user_config:update',
        ];
        wrapper = await createWrapper();

        await flushPromises();
    });

    afterEach(async () => {
        if (wrapper) {
            await wrapper.destroy();
        }

        await flushPromises();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should reject when acl rights are missing', async () => {
        global.activeAclRoles = [];

        await expect(wrapper.vm.getUserSettingsEntity(
            'my-identifier',
            'my-user-id',
        )).rejects.toEqual();
    });

    it('should receives the whole settings entity via identifier key', async () => {
        createRepositoryFactoryMock = {
            search: jest.fn(() => Promise.resolve([
                {
                    id: 'my-user-id',
                    key: 'my-identifier',
                },
            ])),
        };

        const result = await wrapper.vm.getUserSettingsEntity(
            'my-identifier',
            'my-user-id',
        );

        expect(result).toEqual({
            id: 'my-user-id',
            key: 'my-identifier',
        });

        expect(createRepositoryFactoryMock.search).toHaveBeenCalledWith(
            expect.objectContaining({
                filters: [
                    {
                        field: 'key',
                        type: 'equals',
                        value: 'my-identifier',
                    },
                    {
                        field: 'userId',
                        type: 'equals',
                        value: 'my-user-id',
                    },
                ],
            }),
            expect.anything(),
        );
    });

    it('should return null via identifier key when no result was given', async () => {
        createRepositoryFactoryMock = {
            search: jest.fn(() => Promise.resolve([])),
        };

        const result = await wrapper.vm.getUserSettingsEntity(
            'my-identifier',
            'my-user-id',
        );

        expect(result).toBeNull();

        expect(createRepositoryFactoryMock.search).toHaveBeenCalledWith(
            expect.objectContaining({
                filters: [
                    {
                        field: 'key',
                        type: 'equals',
                        value: 'my-identifier',
                    },
                    {
                        field: 'userId',
                        type: 'equals',
                        value: 'my-user-id',
                    },
                ],
            }),
            expect.anything(),
        );
    });

    it('should return the user settings value', async () => {
        createRepositoryFactoryMock = {
            search: jest.fn(() => Promise.resolve([
                {
                    value: {
                        my: 'value',
                    },
                },
            ])),
        };

        const result = await wrapper.vm.getUserSettings(
            'my-identifier',
            'my-user-id',
        );

        expect(result).toEqual({
            my: 'value',
        });

        expect(createRepositoryFactoryMock.search).toHaveBeenCalledWith(
            expect.objectContaining({
                filters: [
                    {
                        field: 'key',
                        type: 'equals',
                        value: 'my-identifier',
                    },
                    {
                        field: 'userId',
                        type: 'equals',
                        value: 'my-user-id',
                    },
                ],
            }),
            expect.anything(),
        );
    });

    it('should save the user settings', async () => {
        createRepositoryFactoryMock = {
            search: jest.fn(() => Promise.resolve([
                {
                    value: {
                        my: 'value',
                    },
                },
            ])),
            save: jest.fn(() => Promise.resolve({
                save: 'success',
            })),
        };

        const result = await wrapper.vm.saveUserSettings(
            'my-identifier',
            {
                entity: 'value',
            },
        );

        expect(result).toEqual({
            save: 'success',
        });

        expect(createRepositoryFactoryMock.save).toHaveBeenCalledWith(
            expect.objectContaining({
                key: 'custom.my-identifier',
                userId: undefined,
                value: {
                    entity: 'value',
                },
            }),
            expect.anything(),
        );
    });
});
