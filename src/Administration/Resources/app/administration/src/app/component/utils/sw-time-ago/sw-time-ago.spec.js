import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/app/component/utils/sw-time-ago';

// mock Date.now() to 2025-06-24 15:00
Date.now = jest.fn(
    () => new Date(Date.UTC(2025, 5, 24, 15, 0)).valueOf()
);


function createWrapper(propsData = {}) {
    const localVue = createLocalVue();

    localVue.directive('tooltip', {
        bind(el, binding) {
            el.setAttribute('tooltip-message', binding.value.message);
            el.setAttribute('tooltip-disabled', binding.value.disabled);
        },
        inserted(el, binding) {
            el.setAttribute('tooltip-message', binding.value.message);
            el.setAttribute('tooltip-disabled', binding.value.disabled);
        },
        update(el, binding) {
            el.setAttribute('tooltip-message', binding.value.message);
            el.setAttribute('tooltip-disabled', binding.value.disabled);
        }
    });

    return shallowMount(Shopware.Component.build('sw-time-ago'), {
        localVue,
        propsData: {
            ...propsData
        },
        mocks: {
            $tc: (snippetPath, count, values) => snippetPath + count + JSON.stringify(values)
        }
    });
}

describe('src/app/component/utils/sw-time-ago', () => {
    /** @type Wrapper */
    let wrapper;

    beforeAll(async () => {});

    beforeEach(() => {});

    afterEach(async () => {
        if (wrapper) await wrapper.destroy();
    });

    describe('date property as string', () => {
        it('should be a Vue.JS component', async () => {
            wrapper = await createWrapper({ date: '2025-06-24T15:00:00.000+00:00' });

            expect(wrapper.vm).toBeTruthy();
        });

        it('should show the correct time for less than one minute', async () => {
            wrapper = await createWrapper({
                date: '2025-06-24T15:00:00.000+00:00'
            });

            expect(wrapper.text()).toContain('global.sw-time-ago.justNow');
        });

        it('should show the correct time for less than one hour', async () => {
            wrapper = await createWrapper({
                date: '2025-06-24T14:30:00.000+00:00'
            });

            expect(wrapper.text()).toContain('global.sw-time-ago.minutesAgo');
        });

        it('should show the correct time for today', async () => {
            wrapper = await createWrapper({
                date: '2025-06-24T08:25:00.000+00:00'
            });

            expect(wrapper.text()).toContain('8:25');
        });

        it('should show the correct time for days more than one day ago', async () => {
            wrapper = await createWrapper({
                date: '2025-06-16T15:00:00.000+00:00'
            });

            // Full check is not possible because node.js does not support full-icu support before version 13.
            // Therefore the server tests show different expect results than on the local machine
            expect(wrapper.text()).toContain('16');
        });

        it('should show a tooltip when day is today', async () => {
            wrapper = await createWrapper({
                date: '2025-06-24T14:30:00.000+00:00'
            });

            expect(wrapper.find('span').attributes('tooltip-disabled')).toBe('false');
        });

        it('should not show a tooltip when day is not today', async () => {
            wrapper = await createWrapper({
                date: '2025-06-21T14:30:00.000+00:00'
            });

            expect(wrapper.find('span').attributes('tooltip-disabled')).toBe('true');
        });
    });

    describe('date property as object', () => {
        it('should be a Vue.JS component', async () => {
            wrapper = await createWrapper({ date: new Date('2025-06-24T15:00:00.000+00:00') });

            expect(wrapper.vm).toBeTruthy();
        });

        it('should show the correct time for less than one minute', async () => {
            wrapper = await createWrapper({
                date: new Date('2025-06-24T15:00:00.000+00:00')
            });

            expect(wrapper.text()).toContain('global.sw-time-ago.justNow');
        });

        it('should show the correct time for less than one hour', async () => {
            wrapper = await createWrapper({
                date: new Date('2025-06-24T14:30:00.000+00:00')
            });

            expect(wrapper.text()).toContain('global.sw-time-ago.minutesAgo');
        });

        it('should show the correct time for today', async () => {
            wrapper = await createWrapper({
                date: new Date('2025-06-24T08:25:00.000+00:00')
            });

            expect(wrapper.text()).toContain('8:25');
        });

        it('should show the correct time for days more than one day ago', async () => {
            wrapper = await createWrapper({
                date: new Date('2025-06-16T15:00:00.000+00:00')
            });

            // Full check is not possible because node.js does not support full-icu support before version 13.
            // Therefore the server tests show different expect results than on the local machine
            expect(wrapper.text()).toContain('16');
        });

        it('should show a tooltip when day is today', async () => {
            wrapper = await createWrapper({
                date: new Date('2025-06-24T14:30:00.000+00:00')
            });

            expect(wrapper.find('span').attributes('tooltip-disabled')).toBe('false');
        });

        it('should not show a tooltip when day is not today', async () => {
            wrapper = await createWrapper({
                date: new Date('2025-06-21T14:30:00.000+00:00')
            });

            expect(wrapper.find('span').attributes('tooltip-disabled')).toBe('true');
        });
    });
});
