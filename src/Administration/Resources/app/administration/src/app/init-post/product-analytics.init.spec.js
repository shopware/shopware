import useConsentStore from 'src/core/consent/consent.store';
import consentEventHandler from 'src/core/telemetry/product-analytics/consent-event-handler';
import telemetryEventHandler from 'src/core/telemetry/product-analytics/telemetry-event-handler';
import initProductAnalytics from './product-analytics.init';

const mockDeleteUser = jest.fn();
const mockInit = jest.fn(function () {
    this.isInitialized = true;
});
const mockFlush = jest.fn().mockResolvedValue(undefined);
const mockFlushWithoutRetry = jest.fn().mockResolvedValue(undefined);
const mockClearStorage = jest.fn();
const mockSetOptOut = jest.fn();

jest.mock('src/core/telemetry/product-analytics/consent-event-handler', () => {
    return jest.fn(() => jest.fn());
});
jest.mock('src/core/telemetry/product-analytics/telemetry-event-handler', () => {
    return jest.fn(() => jest.fn());
});

jest.mock('src/core/telemetry/product-analytics/gateway-client', () => {
    return {
        GatewayClient: jest.fn().mockImplementation(() => ({
            deleteUser: mockDeleteUser,
            isInitialized: false,
            init: mockInit,
            flush: mockFlush,
            flushWithoutRetry: mockFlushWithoutRetry,
            clearStorage: mockClearStorage,
            setOptOut: mockSetOptOut,
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

    function getRegisteredHandler(mockedFactory, index = 0) {
        return mockedFactory.mock.results[index]?.value;
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

            const registeredConsentHandler = getRegisteredHandler(consentEventHandler);

            expect(consentEventHandler).toHaveBeenCalled();
            expect(onSpy).toHaveBeenCalledTimes(1);
            expect(registeredConsentHandler).toEqual(expect.any(Function));
            expect(onSpy).toHaveBeenCalledWith('consent', registeredConsentHandler);
        });

        it('does not initialize client without product analytics consent', async () => {
            watchHandle = await initProductAnalytics();

            expect(mockInit).not.toHaveBeenCalled();
        });

        it('does not initialize client with stale product analytics consent', async () => {
            useConsentStore().consents.product_analytics = {
                name: 'product_analytics',
                status: 'accepted',
                acceptedRevision: '2026-02-01',
                latestRevision: '2026-02-02',
            };

            watchHandle = await initProductAnalytics();

            expect(mockInit).not.toHaveBeenCalled();
        });
    });

    describe('product analytics consent handling', () => {
        it('initializes the client when consent was given', async () => {
            useConsentStore().consents.product_analytics = {
                name: 'product_analytics',
                status: 'accepted',
                acceptedRevision: '2026-02-02',
                latestRevision: '2026-02-02',
            };
            const { onSpy, offSpy } = getEventBusSpies();

            watchHandle = await initProductAnalytics();

            const registeredTelemetryHandler = getRegisteredHandler(telemetryEventHandler);

            expect(mockInit).toHaveBeenCalled();
            expect(onSpy).toHaveBeenCalledTimes(2);
            expect(registeredTelemetryHandler).toEqual(expect.any(Function));
            expect(onSpy).toHaveBeenNthCalledWith(2, 'telemetry', registeredTelemetryHandler);
            expect(offSpy).not.toHaveBeenCalled();
            expect(mockDeleteUser).not.toHaveBeenCalled();
            expect(mockClearStorage).not.toHaveBeenCalled();
            expect(mockSetOptOut).toHaveBeenLastCalledWith(false);
        });

        it('removes telemetry handler when consent gets revoked', async () => {
            useConsentStore().consents.product_analytics = {
                name: 'product_analytics',
                status: 'accepted',
                acceptedRevision: '2026-02-02',
                latestRevision: '2026-02-02',
            };
            const { onSpy, offSpy } = getEventBusSpies();

            watchHandle = await initProductAnalytics();

            const registeredTelemetryHandler = getRegisteredHandler(telemetryEventHandler);

            expect(mockInit).toHaveBeenCalled();
            expect(onSpy).toHaveBeenNthCalledWith(2, 'telemetry', registeredTelemetryHandler);
            expect(offSpy).not.toHaveBeenCalled();

            useConsentStore().consents.product_analytics.status = 'revoked';
            await flushPromises();

            expect(offSpy).toHaveBeenCalled();
            expect(offSpy).toHaveBeenCalledWith('telemetry', registeredTelemetryHandler);
            expect(mockFlushWithoutRetry).toHaveBeenCalled();
            expect(mockSetOptOut).toHaveBeenLastCalledWith(true);
        });

        it('sends delete user request when consent is revoked', async () => {
            useConsentStore().consents.product_analytics = {
                name: 'product_analytics',
                status: 'accepted',
                acceptedRevision: '2026-02-02',
                latestRevision: '2026-02-02',
            };

            watchHandle = await initProductAnalytics();

            useConsentStore().consents.product_analytics.status = 'revoked';
            await flushPromises();

            expect(mockDeleteUser).toHaveBeenCalled();
            expect(mockDeleteUser).toHaveBeenCalledWith('knneBsx7LiKySnUq', '8b8ebef4-7fa3-4844-ab7e-120463ea558b');
        });

        it('clears storage when consent is revoked', async () => {
            jest.useFakeTimers();

            useConsentStore().consents.product_analytics = {
                name: 'product_analytics',
                status: 'accepted',
                acceptedRevision: '2026-02-02',
                latestRevision: '2026-02-02',
            };

            watchHandle = await initProductAnalytics();

            useConsentStore().consents.product_analytics.status = 'revoked';
            await flushPromises();
            jest.runAllTimers();

            expect(mockClearStorage).toHaveBeenCalled();

            jest.useRealTimers();
        });

        it('flushes queued events before deleting the user on consent revocation', async () => {
            let resolveFlushWithoutRetry;
            mockFlushWithoutRetry.mockImplementationOnce(
                () =>
                    new Promise((resolve) => {
                        resolveFlushWithoutRetry = resolve;
                    }),
            );
            useConsentStore().consents.product_analytics.status = 'accepted';

            watchHandle = await initProductAnalytics();

            useConsentStore().consents.product_analytics.status = 'revoked';
            await flushPromises();

            expect(mockFlushWithoutRetry).toHaveBeenCalled();
            expect(mockDeleteUser).not.toHaveBeenCalled();

            resolveFlushWithoutRetry();
            await flushPromises();

            expect(mockDeleteUser).toHaveBeenCalledWith(testShopId, testUserId);
            expect(mockClearStorage).toHaveBeenCalled();
        });

        it('Does not initialize the client twice after consent was revoked and accepted again', async () => {
            useConsentStore().consents.product_analytics = {
                name: 'product_analytics',
                status: 'accepted',
                acceptedRevision: '2026-02-02',
                latestRevision: '2026-02-02',
            };

            watchHandle = await initProductAnalytics();

            useConsentStore().consents.product_analytics.status = 'revoked';
            await flushPromises();

            useConsentStore().consents.product_analytics = {
                name: 'product_analytics',
                status: 'accepted',
                acceptedRevision: '2026-02-02',
                latestRevision: '2026-02-02',
            };
            await flushPromises();

            expect(mockInit).toHaveBeenCalledTimes(1);
        });
    });
});
