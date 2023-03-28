import addPluginUpdatesListener from 'src/core/service/plugin-updates-listener.service';

const oneDay = 24 * 60 * 60 * 1000;
const currentTime = 1671840015000;

const localStorageKey = 'lastPluginUpdateCheck';
jest.useFakeTimers('modern');
jest.setSystemTime(currentTime);

describe('src/core/service/plugin-update-listener.service.ts', () => {
    function createServiceContainer(privileges) {
        return {
            storeService: {
                getUpdateList: () => {
                    return Promise.resolve({
                        total: 10
                    });
                }
            },
            acl: {
                can: (privilegeKey) => {
                    return privileges.includes(privilegeKey);
                }
            }
        };
    }

    function getApplicationRoot(dispatchFunction) {
        return {
            $tc: (snippet) => {
                return snippet;
            },
            $store: {
                dispatch: dispatchFunction
            }
        };
    }

    describe('checksForUpdatesAndShowsNotification', () => {
        const dispatchFunctionMock = jest.fn();
        beforeEach(() => {
            const lastCheckDate = (currentTime - oneDay - 1).toString();
            localStorage.setItem(localStorageKey, lastCheckDate.toString());

            const applicationRoot = getApplicationRoot(dispatchFunctionMock);
            jest.spyOn(Shopware.Application, 'getApplicationRoot').mockImplementation(() => { return { ...applicationRoot }; });

            addPluginUpdatesListener(null, createServiceContainer(['plugin:update', 'app.all']));

            Shopware.State.commit('setCurrentUser', {
                firstName: 'userFirstName'
            });
        });

        it('should update the key and show a notification', async () => {
            const expectedDate = currentTime.toString();

            expect(localStorage.getItem(localStorageKey)).toBe(expectedDate);
            expect(dispatchFunctionMock).toHaveBeenCalled();
        });
    });

    describe('checksForUpdatesButDoesNotShowsNotification', () => {
        const dispatchFunctionMock = jest.fn();
        beforeEach(() => {
            const lastCheckDate = (currentTime - oneDay - 1).toString();
            localStorage.setItem(localStorageKey, lastCheckDate.toString());

            const applicationRoot = getApplicationRoot(dispatchFunctionMock);
            jest.spyOn(Shopware.Application, 'getApplicationRoot').mockImplementation(() => { return { ...applicationRoot }; });

            // no permissions given
            addPluginUpdatesListener(null, createServiceContainer([]));

            Shopware.State.commit('setCurrentUser', {
                firstName: 'userFirstName'
            });
        });

        it('should update the key but not show a notification if no permissions are given', async () => {
            const expectedDate = currentTime.toString();

            expect(localStorage.getItem(localStorageKey)).toBe(expectedDate);
            expect(dispatchFunctionMock).not.toHaveBeenCalled();
        });
    });

    describe('doesNotCheckForUpdatesTwiceADay', () => {
        beforeEach(() => {
            // less than one day ago
            const lastCheckDate = (currentTime - oneDay).toString();

            localStorage.setItem(localStorageKey, lastCheckDate.toString());

            addPluginUpdatesListener(null, null);
            Shopware.State.commit('setCurrentUser', {
                firstName: 'userFirstName'
            });
        });

        it('should only update the key if it checked for updates', async () => {
            const expectedDate = (currentTime - oneDay).toString();

            expect(localStorage.getItem(localStorageKey)).toBe(expectedDate);
        });
    });

    describe('doesNothingIfNoUserChanged', () => {
        beforeEach(() => {
            const lastCheckDate = (currentTime - oneDay - 1).toString();
            localStorage.setItem(localStorageKey, lastCheckDate.toString());

            Shopware.State.commit('setCurrentUser', null);

            addPluginUpdatesListener(null, null);

            // should not trigger the check because the user was not changed
            Shopware.State.commit('setCurrentUser', null);
        });

        it('should not check if no user was changed', async () => {
            const expectedDate = (currentTime - oneDay - 1).toString();

            expect(localStorage.getItem(localStorageKey)).toBe(expectedDate);
        });
    });
});
