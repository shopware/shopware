import useConsentStore from 'src/core/consent/consent.store';
import consentEventHandler from 'src/core/telemetry/product-analytics/consent-event-handler';
import telemetryEventHandler from 'src/core/telemetry/product-analytics/telemetry-event-handler';
import initProductAnalytics from './product-analytics.init';

const mockDeleteUser = jest.fn();
const mockInit = jest.fn(function () {
    this.isInitialized = true;
});
const mockFlush = jest.fn();
const mockClearStorage = jest.fn();

jest.mock('src/core/telemetry/product-analytics/consent-event-handler', () => {
    return jest.fn().mockReturnValue('consent-event-handler');
});
jest.mock('src/core/telemetry/product-analytics/telemetry-event-handler', () => {
    return jest.fn().mockReturnValue('telemetry-event-handler');
});

jest.mock('src/core/telemetry/product-analytics/gateway-client', () => {
    return {
        GatewayClient: jest.fn().mockImplementation(() => ({
            deleteUser: mockDeleteUser,
            isInitialized: false,
            init: mockInit,
            flush: mockFlush,
            clearStorage: mockClearStorage,
        })),
    };
});

describe('src/app/post-init/product-analytics.init.ts', () => {
    const testShopId = 'knneBsx7LiKySnUq';
    const testUserId = '8b8ebef4-7fa3-4844-ab7e-120463ea558b';
    let watchHandle;

    beforeAll(() => {
        Shopware.Service().register('loginService', () => {
            return {
                addOnLogoutListener: jest.fn(),
            };
        });
    });

    beforeEach(() => {
        jest.clearAllMocks();

        watchHandle?.();
        Shopware.Utils.EventBus.all?.clear();

        Shopware.Store.get('context').app.analyticsGatewayUrl = 'https://gateway.example';
        Shopware.Store.get('context').app.config.shopId = testShopId;
        Shopware.Store.get('session').currentUser = {
            id: testUserId,
        };

        useConsentStore().consents = {
            product_analytics: {
                name: 'product_analytics',
                status: 'revoked',
            },
        };

        global.repositoryFactoryMock.responses.addResponse({
            method: 'Post',
            url: '/search/language',
            status: 200,
            response: {
                data: [
                    {
                        id: 'language-id',
                        attributes: {
                            name: 'English',
                        },
                    },
                ],
            },
        });
    });

    function getEventBusSpies() {
        const onSpy = jest.spyOn(Shopware.Utils.EventBus, 'on');
        const offSpy = jest.spyOn(Shopware.Utils.EventBus, 'off');

        return { onSpy, offSpy };
    }

    describe('initialization', () => {
        it('does not initialize if gateway url is missing', async () => {
            Shopware.Store.get('context').app.analyticsGatewayUrl = null;
            const { onSpy, offSpy } = getEventBusSpies();

            watchHandle = await initProductAnalytics();

            expect(onSpy).not.toHaveBeenCalled();
            expect(offSpy).not.toHaveBeenCalled();
            expect(consentEventHandler).not.toHaveBeenCalled();
            expect(telemetryEventHandler).not.toHaveBeenCalled();
        });

        it('initializes consent metrics if gateway url is set', async () => {
            const { onSpy } = getEventBusSpies();

            watchHandle = await initProductAnalytics();

            expect(consentEventHandler).toHaveBeenCalled();
            expect(onSpy).toHaveBeenCalledTimes(1);
            expect(onSpy).toHaveBeenCalledWith('consent', 'consent-event-handler');
        });

        it('does not initialize client without product analytics consent', async () => {
            watchHandle = await initProductAnalytics();

            expect(mockInit).not.toHaveBeenCalled();
        });
    });

    describe('product analytics consent handling', () => {
        it('initializes the client when consent was given', async () => {
            useConsentStore().consents.product_analytics.status = 'accepted';
            const { onSpy, offSpy } = getEventBusSpies();

            watchHandle = await initProductAnalytics();

            expect(mockInit).toHaveBeenCalled();
            expect(onSpy).toHaveBeenCalledTimes(2);
            expect(onSpy).toHaveBeenNthCalledWith(2, 'telemetry', 'telemetry-event-handler');
            expect(offSpy).not.toHaveBeenCalled();
            expect(mockDeleteUser).not.toHaveBeenCalled();
            expect(mockClearStorage).not.toHaveBeenCalled();
        });

        it('removes telemetry handler when consent gets revoked', async () => {
            useConsentStore().consents.product_analytics.status = 'accepted';
            const { onSpy, offSpy } = getEventBusSpies();

            watchHandle = await initProductAnalytics();

            expect(mockInit).toHaveBeenCalled();
            expect(onSpy).toHaveBeenNthCalledWith(2, 'telemetry', 'telemetry-event-handler');
            expect(offSpy).not.toHaveBeenCalled();

            useConsentStore().consents.product_analytics.status = 'revoked';
            await flushPromises();

            expect(offSpy).toHaveBeenCalled();
            expect(offSpy).toHaveBeenCalledWith('telemetry', 'telemetry-event-handler');
        });

        it('sends delete user request when consent is revoked', async () => {
            useConsentStore().consents.product_analytics.status = 'accepted';

            watchHandle = await initProductAnalytics();

            useConsentStore().consents.product_analytics.status = 'revoked';
            await flushPromises();

            expect(mockDeleteUser).toHaveBeenCalled();
            expect(mockDeleteUser).toHaveBeenCalledWith('knneBsx7LiKySnUq', '8b8ebef4-7fa3-4844-ab7e-120463ea558b');
        });

        it('clears storage when consent is revoked', async () => {
            jest.useFakeTimers();

            useConsentStore().consents.product_analytics.status = 'accepted';

            watchHandle = await initProductAnalytics();

            useConsentStore().consents.product_analytics.status = 'revoked';
            await flushPromises();
            jest.runAllTimers();

            expect(mockClearStorage).toHaveBeenCalled();

            jest.useRealTimers();
        });

        it('Does not initialize the client twice after consent was revoked and accepted again', async () => {
            useConsentStore().consents.product_analytics.status = 'accepted';

            watchHandle = await initProductAnalytics();

            useConsentStore().consents.product_analytics.status = 'revoked';
            await flushPromises();

            useConsentStore().consents.product_analytics.status = 'accepted';
            await flushPromises();

            expect(mockInit).toHaveBeenCalledTimes(1);
        });
    });
});
