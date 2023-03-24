/**
 * @package admin
 */

import { shallowMount } from '@vue/test-utils';
import 'src/app/component/utils/sw-error-boundary';

describe('src/app/component/utils/sw-error-boundary', () => {
    /** @type Wrapper */
    let wrapper;
    let swErrorBoundary;

    beforeAll(async () => {
        swErrorBoundary = await Shopware.Component.build('sw-error-boundary');
    });

    beforeEach(async () => {
        jest.spyOn(console, 'error').mockImplementation();
    });

    afterEach(async () => {
        await flushPromises();
        global.repositoryFactoryMock.clientMock.resetHistory();
        if (wrapper) await wrapper.destroy();
        if (console.error.mockReset) console.error.mockReset();
    });

    it('should be a Vue.js component', async () => {
        wrapper = shallowMount(swErrorBoundary);

        expect(wrapper.vm).toBeTruthy();
    });

    it('should catch the error from siblings', async () => {
        expect(console.error).not.toHaveBeenCalled();

        wrapper = shallowMount(swErrorBoundary, {
            slots: {
                default: '<sw-damaged-component></sw-damaged-component>'
            },
            stubs: {
                'sw-damaged-component': {
                    template: '<div class="sw-damaged-component"></div>',
                    mounted() {
                        throw new Error('There is gone something wrong');
                    }
                }
            }
        });

        expect(console.error).toHaveBeenCalledWith(
            'An error was captured in current module:',
            new Error('There is gone something wrong')
        );
    });

    it('should log the error to the error logs', async () => {
        wrapper = shallowMount(swErrorBoundary, {
            slots: {
                default: '<sw-damaged-component></sw-damaged-component>'
            },
            stubs: {
                'sw-damaged-component': {
                    template: '<div class="sw-damaged-component"></div>',
                    mounted() {
                        throw new Error('There is gone something wrong');
                    }
                }
            }
        });

        const postHistory = global.repositoryFactoryMock.clientMock.history.post;

        expect(postHistory.length).toBe(0);

        // wait until the component finished all requests
        await flushPromises();
        expect(postHistory.length).toBe(1);

        // should send post request for logging the error
        expect(postHistory[0].url).toEqual('/log-entry');
        expect(JSON.parse(postHistory[0].data).level).toEqual(400);
        expect(JSON.parse(postHistory[0].data).channel).toEqual('Administration');
        expect(JSON.parse(postHistory[0].data).message).toEqual('Error: There is gone something wrong');
    });
});
