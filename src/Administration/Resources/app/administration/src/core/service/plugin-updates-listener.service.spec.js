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

    it('should not update the key if the notification could not be shown', async () => {
        const lastCheckDate = (currentTime - oneDay - 1).toString();
        localStorage.setItem(localStorageKey, lastCheckDate);

        // no application root given => no notification can be dispatched => localStorageKey should not be updated
        jest.spyOn(Shopware.Application, 'getApplicationRoot').mockImplementation(() => { return false; });

        addPluginUpdatesListener(null, createServiceContainer(['plugin:update', 'app.all']));
        Shopware.State.commit('setCurrentUser', {
            firstName: 'userFirstName'
        });

        await flushPromises();

        expect(localStorage.getItem(localStorageKey)).toBe(lastCheckDate);
    });

    it('should update the key and show a notification', async () => {
        const dispatchFunctionMock = jest.fn();
        const lastCheckDate = (currentTime - oneDay - 1).toString();
        localStorage.setItem(localStorageKey, lastCheckDate);

        const applicationRoot = getApplicationRoot(dispatchFunctionMock);
        jest.spyOn(Shopware.Application, 'getApplicationRoot').mockImplementation(() => { return { ...applicationRoot }; });

        addPluginUpdatesListener(null, createServiceContainer(['plugin:update', 'app.all']));

        Shopware.State.commit('setCurrentUser', {
            firstName: 'userFirstName'
        });

        await flushPromises();

        const expectedDate = currentTime.toString();

        expect(localStorage.getItem(localStorageKey)).toBe(expectedDate);
        expect(dispatchFunctionMock).toHaveBeenCalled();
    });

    it('should only update the key if it checked for updates', async () => {
        // less than one day ago
        const lastCheckDate = (currentTime - oneDay).toString();

        localStorage.setItem(localStorageKey, lastCheckDate);

        addPluginUpdatesListener(null, null);
        Shopware.State.commit('setCurrentUser', {
            firstName: 'userFirstName'
        });

        await flushPromises();

        const expectedDate = (currentTime - oneDay).toString();

        expect(localStorage.getItem(localStorageKey)).toBe(expectedDate);
    });

    it('should not check if no user was changed', async () => {
        const lastCheckDate = (currentTime - oneDay - 1).toString();
        localStorage.setItem(localStorageKey, lastCheckDate);

        Shopware.State.commit('setCurrentUser', null);
        await flushPromises();

        addPluginUpdatesListener(null, null);

        // should not trigger the check because the user was not changed
        Shopware.State.commit('setCurrentUser', null);
        await flushPromises();

        const expectedDate = (currentTime - oneDay - 1).toString();

        expect(localStorage.getItem(localStorageKey)).toBe(expectedDate);
    });
});
