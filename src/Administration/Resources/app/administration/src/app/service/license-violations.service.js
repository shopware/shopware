const { Application } = Shopware;

export default function createLicenseViolationsService(storeService) {
    /** {VueInstance|null} applicationRoot  */
    let applicationRoot = null;

    const lastLicenseWarningsKey = 'lastLicenseWarningsShowed';
    const lastLicenseFetchedKey = 'lastLicenseViolationsFetched';
    const responseCacheKey = 'licenseViolationCache';
    const showViolationsKey = 'licenseViolationShowViolations';

    return {
        checkForLicenseViolations,
        saveTimeToLocalStorage,
        removeTimeFromLocalStorage,
        resetLicenseViolations,
        forceDeletePlugin,
        isTimeExpired,
        filterWarnings,
        ignorePlugin,
        getIgnoredPlugins,
        getViolationsFromCache,
        saveViolationsToCache,
        key: {
            lastLicenseWarningsKey,
            lastLicenseFetchedKey,
            responseCacheKey,
            showViolationsKey,
        },
    };

    function checkForLicenseViolations() {
        const topLevelDomain = window.location.hostname.split('.').pop();
        const whitelistDomains = [
            'localhost',
            'test',
            'local',
            'invalid',
            'development',
            'vm',
            'next',
            'example',
        ];

        // if the user is on a whitelisted domain
        if (whitelistDomains.includes(topLevelDomain)) {
            return Promise.resolve({
                warnings: [],
                violations: [],
                other: [],
            });
        }

        // if last request is not older than 24 hours
        if (!isTimeExpired(lastLicenseFetchedKey)) {
            const cachedViolations = getViolationsFromCache();

            // handle response with cached violations
            return handleResponse(cachedViolations);
        }

        return fetchLicenseViolations()
            .then((response) => {
                if (!response) {
                    return Promise.reject();
                }

                const licenseViolations = response.filter((i) => i.extensions.licenseViolation);

                saveViolationsToCache(licenseViolations);

                return handleResponse(licenseViolations);
            });
    }

    function handleResponse(response) {
        const resolveData = {
            violations: response.filter((violation) => violation.extensions.licenseViolation.type.level === 'violation'),
            warnings: response.filter((violation) => violation.extensions.licenseViolation.type.level === 'warning'),
            other: response.filter((violation) => {
                return violation.extensions.licenseViolation.type.level !== 'violation'
                    && violation.extensions.licenseViolation.type.level !== 'warning';
            }),
        };

        if (isTimeExpired(lastLicenseWarningsKey)) {
            const pluginsToIgnore = getIgnoredPlugins();
            const filteredWarnings = filterWarnings(resolveData.warnings, pluginsToIgnore);
            showWarnings(filteredWarnings);

            saveTimeToLocalStorage(lastLicenseWarningsKey);
        }

        if (isTimeExpired(lastLicenseFetchedKey)) {
            saveTimeToLocalStorage(lastLicenseFetchedKey);
        }

        return Promise.resolve(resolveData);
    }

    function saveViolationsToCache(response) {
        if (typeof response !== 'object') {
            return;
        }

        const stringResponse = JSON.stringify(response);
        localStorage.setItem(responseCacheKey, stringResponse);
    }

    function getViolationsFromCache() {
        const stringValue = localStorage.getItem(responseCacheKey);
        return JSON.parse(stringValue);
    }

    function isTimeExpired(key) {
        const actualDate = new Date();
        const lastCheck = localStorage.getItem(key);

        if (!lastCheck) {
            return true;
        }

        const timeDifference = actualDate.getTime() - Number(lastCheck);

        return timeDifference > 1000 * 60 * 60 * 24;
    }

    function saveTimeToLocalStorage(key) {
        const actualDate = new Date();

        localStorage.setItem(key, String(actualDate.getTime()));
    }

    function getApplicationRootReference() {
        if (!applicationRoot) {
            applicationRoot = Application.getApplicationRoot();
        }

        return applicationRoot;
    }

    function fetchLicenseViolations() {
        return storeService.getLicenseViolationList().then((response) => {
            return response.items;
        });
    }

    function resetLicenseViolations() {
        localStorage.removeItem(showViolationsKey);
        localStorage.removeItem(lastLicenseFetchedKey);
        localStorage.removeItem(responseCacheKey);
    }

    async function forceDeletePlugin(extension) {
        const shopwareExtensionService = Shopware.Service('shopwareExtensionService');
        const cacheService = Shopware.Service('cacheApiService');

        try {
            const isActive = extension.active;
            const isInstalled = extension.installedAt !== null;

            if (isActive) {
                await shopwareExtensionService.deactivateExtension(extension.name, extension.type);
                await cacheService.clear();
            }

            if (isInstalled) {
                await shopwareExtensionService.uninstallExtension(extension.name, extension.type);
            }

            await shopwareExtensionService.removeExtension(extension.name, extension.type);

            return true;
        } catch (error) {
            throw new Error(error);
        }
    }

    function spawnNotification(plugin) {
        const warning = plugin.extensions.licenseViolation;
        const notificationActions = warning.actions.map((action) => {
            return {
                label: action.label,
                route: action.externalLink,
            };
        });

        const ignorePluginAction = {
            label: getApplicationRootReference().$tc('sw-license-violation.ignorePlugin'),
            method: () => ignorePlugin(warning.name, getIgnoredPlugins()),
        };

        getApplicationRootReference().$store.dispatch('notification/createGrowlNotification', {
            title: plugin.label,
            message: warning.text,
            autoClose: false,
            variant: 'warning',
            actions: [
                ...notificationActions,
                ignorePluginAction,
            ],
        });
    }

    function ignorePlugin(pluginName, pluginsToIgnore) {
        if (!pluginName) {
            return;
        }

        pluginsToIgnore.push(pluginName);

        localStorage.setItem('ignorePluginWarning', JSON.stringify(pluginsToIgnore));
    }

    function getIgnoredPlugins() {
        const ignorePluginWarning = localStorage.getItem('ignorePluginWarning');

        if (!ignorePluginWarning) {
            return [];
        }

        return JSON.parse(ignorePluginWarning);
    }

    function showWarnings(warnings) {
        warnings.forEach((warning) => spawnNotification(warning));
    }

    function filterWarnings(warnings, pluginsToIgnore) {
        return warnings.reduce((acc, warning) => {
            if (pluginsToIgnore.includes(warning.name)) {
                return acc;
            }

            acc.push(warning);
            return acc;
        }, []);
    }

    function removeTimeFromLocalStorage(key) {
        localStorage.removeItem(key);
    }
}
