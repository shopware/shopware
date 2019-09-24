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
        key: {
            lastLicenseWarningsKey,
            lastLicenseFetchedKey,
            responseCacheKey,
            showViolationsKey
        }
    };

    function checkForLicenseViolations() {
        const topLevelDomain = window.location.hostname.split('.').pop();
        const whitelistDomains = [
            'localhost',
            'test',
            'local',
            'invalid',
            'development',
            'example'
        ];

        // if the user is on a whitelisted domain
        if (whitelistDomains.includes(topLevelDomain)) {
            return Promise.resolve({
                warnings: [],
                violations: []
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

                saveViolationsToCache(response);

                return handleResponse(response);
            });
    }

    function handleResponse(response) {
        const resolveData = {
            violations: [],
            warnings: []
        };

        const warnings = response.filter((violation) => violation.type.level === 'warning');
        const violations = response.filter((violation) => violation.type.level === 'violation');

        if (isTimeExpired(showViolationsKey) && violations.length > 0) {
            resolveData.violations = violations;
        }

        if (isTimeExpired(lastLicenseWarningsKey)) {
            resolveData.warnings = warnings;
            showWarnings(warnings);
        }

        saveTimeToLocalStorage(lastLicenseWarningsKey);
        saveTimeToLocalStorage(lastLicenseFetchedKey);

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

    function spawnNotification(warning) {
        getApplicationRootReference().$store.dispatch('notification/createGrowlNotification', {
            title: warning.name,
            message: warning.text,
            autoClose: false,
            variant: 'warning',
            actions: warning.actions.map((action) => {
                return {
                    label: action.label,
                    route: action.externalLink
                };
            })
        });
    }

    function showWarnings(warnings) {
        warnings.forEach((warning) => spawnNotification(warning));
    }

    function removeTimeFromLocalStorage(key) {
        localStorage.removeItem(key);
    }
}
