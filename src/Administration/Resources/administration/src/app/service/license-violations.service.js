const { Application } = Shopware;

export default function createLicenseViolationsService(storeService) {
    /** {VueInstance|null} applicationRoot  */
    let applicationRoot = null;
    const violationTypes = {
        warning: 'warning',
        violation: 'error'
    };

    return {
        checkForLicenseViolations
    };

    function checkForLicenseViolations(force = false) {
        if (!shouldCheckValidation() && !force) {
            return;
        }

        fetchLicenseViolations()
            .then((response) => {
                if (!response) {
                    return false;
                }

                setValidationTimer();

                return response.map((violation) => {
                    return spawnNotification(violation);
                });
            });
    }

    function shouldCheckValidation() {
        const actualDate = new Date();
        const lastCheck = localStorage.getItem('licenseViolations');

        if (!lastCheck) {
            return true;
        }

        const timeDifference = actualDate.getTime() - Number(lastCheck);

        return timeDifference > 1000 * 60 * 60 * 24;
    }

    function setValidationTimer() {
        const actualDate = new Date();

        localStorage.setItem('licenseViolations', String(actualDate.getTime()));
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

    function spawnNotification(violation) {
        const violationType = violation.type.level;

        if (!violationTypes.hasOwnProperty(violationType)) {
            return false;
        }

        getApplicationRootReference().$store.dispatch('notification/createGrowlNotification', {
            title: violation.name,
            message: violation.text,
            autoClose: false,
            variant: violationTypes[violationType],
            actions: violation.actions.map((action) => {
                return {
                    label: action.label,
                    route: action.externalLink
                };
            })
        });

        return true;
    }
}
