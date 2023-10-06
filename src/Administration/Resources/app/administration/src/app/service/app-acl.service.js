const { Criteria } = Shopware.Data;

/**
 * @package admin
 *
 * @module app/app-acl-service
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class AppAclService {
    _privileges;

    _appRepository;

    constructor({ privileges, appRepository }) {
        this._privileges = privileges;
        this._appRepository = appRepository;
    }

    getAppPermissions() {
        const criteria = new Criteria(1, 25);
        criteria.addFilter(Criteria.equals('app.active', true));
        const dependencies = [];

        return this._appRepository.search(criteria).then(apps => {
            return apps.map(app => {
                dependencies.push(`app.${app.name}`);

                return {
                    category: 'additional_permissions',
                    parent: null,
                    key: 'app',
                    roles: {
                        [app.name]: {
                            privileges: [],
                            dependencies: [],
                        },
                    },
                };
            });
        }).then((appPermission) => {
            appPermission.push({
                category: 'additional_permissions',
                parent: null,
                key: 'app',
                roles: {
                    all: {
                        privileges: [],
                        dependencies,
                    },
                },
            });

            return appPermission;
        });
    }

    addAppPermissions() {
        return this.getAppPermissions().then(response => {
            this._privileges.addPrivilegeMappingEntries(response);
        });
    }
}
