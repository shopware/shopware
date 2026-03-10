const { Application, Store } = Shopware;

/**
 * @private
 * @sw-package framework
 */
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
        const hostname = window.location.hostname;
        const emptyViolationsResponse = Promise.resolve({
            warnings: [],
            violations: [],
            other: [],
        });

        if (hostname === '[::1]' || hostname === '127.0.0.1') {
            return emptyViolationsResponse;
        }

        const ipv4Match = hostname.match(/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/);

        if (ipv4Match) {
            const octet1 = parseInt(ipv4Match[1], 10);
            const octet2 = parseInt(ipv4Match[2], 10);
            const octet3 = parseInt(ipv4Match[3], 10);
            const octet4 = parseInt(ipv4Match[4], 10);

            // Validate IP address octets
            if (octet1 <= 255 && octet2 <= 255 && octet3 <= 255 && octet4 <= 255) {
                // Private IP ranges (RFC 1918)
                // 10.0.0.0/8
                if (octet1 === 10) {
                    return emptyViolationsResponse;
                }

                // 172.16.0.0/12
                if (octet1 === 172 && octet2 >= 16 && octet2 <= 31) {
                    return emptyViolationsResponse;
                }

                // 192.168.0.0/16
                if (octet1 === 192 && octet2 === 168) {
                    return emptyViolationsResponse;
                }

                // CGNAT IP space (RFC 6598): 100.64.0.0/10
                if (octet1 === 100 && octet2 >= 64 && octet2 <= 127) {
                    return emptyViolationsResponse;
                }
            }
        }

        const hostnameParts = hostname.split('.').pop();
        const allowlistDomains = [
            'localhost',
            'test',
            'local',
            'invalid',
            'development',
            'vm',
            'next',
            'example',
        ];

        // if the user is on a allowlisted domain
        if (allowlistDomains.includes(hostnameParts)) {
            return emptyViolationsResponse;
        }

        // if last request is not older than 24 hours
        if (!isTimeExpired(lastLicenseFetchedKey)) {
            const cachedViolations = getViolationsFromCache();

            // handle response with cached violations
            return handleResponse(cachedViolations);
        }

        return fetchLicenseViolations().then((response) => {
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
                return (
                    violation.extensions.licenseViolation.type.level !== 'violation' &&
                    violation.extensions.licenseViolation.type.level !== 'warning'
                );
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

        Store.get('notification').createGrowlNotification({
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
