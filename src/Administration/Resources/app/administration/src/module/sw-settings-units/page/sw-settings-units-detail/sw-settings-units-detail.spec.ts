/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';

const UNIT_ID = '01929d4d8a5e7c4e8f3b6a1d2c3e4f50';

const { clientMock, responses } = global.repositoryFactoryMock;

type WrapperOptions = {
    unitId?: string | null;
    privileges?: string[];
    customFieldDataProviderService?: {
        getCustomFieldSets: () => Promise<Entity<'custom_field_set'>[]>;
    };
};

function respondWithUnit(): void {
    responses.addResponse({
        method: 'POST',
        url: '/search/unit',
        response: {
            data: [
                {
                    id: UNIT_ID,
                    type: 'unit',
                    attributes: { id: UNIT_ID, name: 'Kilogram', shortCode: 'kg' },
                    relationships: {},
                },
            ],
            meta: { total: 1 },
        },
    });
}

function respondWithServerError(method: 'POST' | 'PATCH', url: string): void {
    responses.addResponse({ method, url, status: 500, response: {} });
}

function respondToSave(method: 'POST' | 'PATCH', url: string): void {
    responses.addResponse({ method, url, status: 204, response: {} });
}

function respondWithBlankNameError(): void {
    responses.addResponse({
        method: 'PATCH',
        url: `/unit/${UNIT_ID}`,
        status: 400,
        response: {
            errors: [
                {
                    code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                    status: '400',
                    detail: 'This value should not be blank.',
                    source: { pointer: '/0/name' },
                },
            ],
        },
    });
}

function requestPayloads(method: 'post' | 'patch', url: string): Record<string, unknown>[] {
    return clientMock.history[method]
        .filter((request) => request.url === url)
        .map((request) => JSON.parse(request.data as string) as Record<string, unknown>);
}

async function createWrapper({
    unitId = null,
    privileges = ['scale_unit.editor'],
    customFieldDataProviderService = { getCustomFieldSets: () => Promise.resolve([]) },
}: WrapperOptions = {}) {
    const router = { push: jest.fn() };

    const wrapper = mount(await wrapTestComponent('sw-settings-units-detail', { sync: true }), {
        props: { unitId },
        global: {
            mocks: { $router: router },
            provide: {
                acl: { can: (privilege: string) => privileges.includes(privilege) },
                customFieldDataProviderService,
            },
            stubs: {
                'sw-page': {
                    template: `
                        <div class="sw-page">
                            <slot name="language-switch"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                        </div>
                    `,
                },
                'sw-language-switch': {
                    props: ['disabled'],
                    emits: ['on-change'],
                    template: `
                        <button class="sw-language-switch" :disabled="disabled" @click="$emit('on-change')"></button>
                    `,
                },
                'sw-button-process': await wrapTestComponent('sw-button-process'),
                'sw-card-view': { template: '<div class="sw-card-view"><slot></slot></div>' },
                'sw-custom-field-set-renderer': true,
                'sw-skeleton': true,
            },
        },
    });

    await flushPromises();

    return { wrapper, router };
}

function saveButton(wrapper: Awaited<ReturnType<typeof createWrapper>>['wrapper']) {
    return wrapper.find('.sw-settings-units__create-action');
}

function notifications() {
    return Object.values(Shopware.Store.get('notification').growlNotifications);
}

describe('module/sw-settings-units/page/sw-settings-units-detail', () => {
    beforeEach(() => {
        clientMock.resetHistory();
        Shopware.Store.get('notification').$reset();
        Shopware.Store.get('error').$reset();
    });

    describe('loading an existing unit', () => {
        it('shows the fields of the requested unit', async () => {
            respondWithUnit();

            const { wrapper } = await createWrapper({ unitId: UNIT_ID });

            expect(requestPayloads('post', '/search/unit')).toEqual([expect.objectContaining({ ids: UNIT_ID })]);
            expect(wrapper.find<HTMLInputElement>('input[name="sw-field--unit-name"]').element.value).toBe('Kilogram');
            expect(wrapper.find<HTMLInputElement>('input[name="sw-field--unit-shortCode"]').element.value).toBe('kg');
            expect(wrapper.find('sw-skeleton-stub').exists()).toBe(false);
        });

        it('shows an error and stops loading when the unit can not be loaded', async () => {
            respondWithServerError('POST', '/search/unit');

            const { wrapper } = await createWrapper({ unitId: UNIT_ID });

            expect(notifications()).toEqual([
                expect.objectContaining({ variant: 'error', message: 'Request failed with status code 500' }),
            ]);
            expect(wrapper.find('sw-skeleton-stub').exists()).toBe(false);
            expect(saveButton(wrapper).attributes('disabled')).toBeUndefined();
            expect(saveButton(wrapper).find('.mt-loader').exists()).toBe(false);
        });

        it('requests the unit again when the language changes', async () => {
            respondWithUnit();
            const { wrapper } = await createWrapper({ unitId: UNIT_ID });

            await wrapper.find('.sw-language-switch').trigger('click');
            await flushPromises();

            expect(requestPayloads('post', '/search/unit')).toHaveLength(2);
        });

        it('shows an error and stops loading when the custom field sets can not be loaded', async () => {
            const { wrapper } = await createWrapper({
                unitId: UNIT_ID,
                customFieldDataProviderService: { getCustomFieldSets: () => Promise.reject(new Error('Network Error')) },
            });

            expect(notifications()).toEqual([
                expect.objectContaining({ variant: 'error', message: 'sw-settings-units.notification.errorMessage' }),
            ]);
            expect(wrapper.find('sw-skeleton-stub').exists()).toBe(false);
        });
    });

    describe('creating a unit', () => {
        it('shows empty fields without requesting a unit', async () => {
            const { wrapper } = await createWrapper();

            expect(requestPayloads('post', '/search/unit')).toHaveLength(0);
            expect(wrapper.find<HTMLInputElement>('input[name="sw-field--unit-name"]').element.value).toBe('');
            expect(wrapper.find('.sw-language-switch').attributes('disabled')).toBeDefined();
        });

        it('creates the unit and opens its detail page', async () => {
            respondToSave('POST', '/unit');
            const { wrapper, router } = await createWrapper();

            await wrapper.find('input[name="sw-field--unit-name"]').setValue('Litre');
            await wrapper.find('input[name="sw-field--unit-shortCode"]').setValue('l');
            await saveButton(wrapper).trigger('click');
            await flushPromises();

            const [payload] = requestPayloads('post', '/unit');
            expect(payload).toMatchObject({ name: 'Litre', shortCode: 'l' });
            expect(router.push).toHaveBeenCalledWith({
                name: 'sw.settings.units.detail',
                params: { id: payload.id },
            });
        });
    });

    describe('saving an existing unit', () => {
        it('sends the changed fields and loads the saved unit', async () => {
            respondWithUnit();
            respondToSave('PATCH', `/unit/${UNIT_ID}`);
            const { wrapper, router } = await createWrapper({ unitId: UNIT_ID });

            await wrapper.find('input[name="sw-field--unit-name"]').setValue('Kilo');
            await saveButton(wrapper).trigger('click');
            await flushPromises();

            expect(requestPayloads('patch', `/unit/${UNIT_ID}`)).toEqual([{ id: UNIT_ID, name: 'Kilo' }]);
            expect(requestPayloads('post', '/search/unit')).toHaveLength(2);
            expect(router.push).not.toHaveBeenCalled();
        });

        it('shows the API errors and stops loading when saving fails', async () => {
            respondWithUnit();
            respondWithBlankNameError();
            const { wrapper, router } = await createWrapper({ unitId: UNIT_ID });

            await wrapper.find('input[name="sw-field--unit-name"]').setValue('');
            await saveButton(wrapper).trigger('click');
            await flushPromises();

            expect(notifications()).toEqual([
                expect.objectContaining({ variant: 'error', message: 'sw-settings-units.notification.errorMessage' }),
            ]);
            expect(wrapper.find('.mt-field__error').text()).toBe('This value should not be blank.');
            expect(saveButton(wrapper).attributes('disabled')).toBeUndefined();
            expect(router.push).not.toHaveBeenCalled();
        });
    });

    describe('permissions', () => {
        it('lets an editor change and save the unit', async () => {
            respondWithUnit();

            const { wrapper } = await createWrapper({ unitId: UNIT_ID, privileges: ['scale_unit.editor'] });

            expect(saveButton(wrapper).attributes('disabled')).toBeUndefined();
            expect(wrapper.find('input[name="sw-field--unit-name"]').attributes('disabled')).toBeUndefined();
            expect(wrapper.find('input[name="sw-field--unit-shortCode"]').attributes('disabled')).toBeUndefined();
            expect(wrapper.find('sw-custom-field-set-renderer-stub').attributes('disabled')).toBe('false');
        });

        it('shows the unit read-only without the editor role', async () => {
            respondWithUnit();

            const { wrapper } = await createWrapper({ unitId: UNIT_ID, privileges: ['scale_unit.viewer'] });

            expect(saveButton(wrapper).attributes('disabled')).toBeDefined();
            expect(wrapper.find('input[name="sw-field--unit-name"]').attributes('disabled')).toBeDefined();
            expect(wrapper.find('input[name="sw-field--unit-shortCode"]').attributes('disabled')).toBeDefined();
            expect(wrapper.find('sw-custom-field-set-renderer-stub').attributes('disabled')).toBe('true');
        });
    });
});
