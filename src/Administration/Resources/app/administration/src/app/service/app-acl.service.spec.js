/**
 * @package admin
 */

import AppAclService from 'src/app/service/app-acl.service';

describe('app/service/app-acl.service.js', () => {
    let appAclService;
    let appPermissionData;

    beforeEach(async () => {
        appPermissionData = [
            {
                category: 'additional_permissions',
                parent: null,
                key: 'app',
                roles: {
                    appExample: {
                        privileges: [],
                        dependencies: [],
                    },
                },
            }, {
                category: 'additional_permissions',
                parent: null,
                key: 'app',
                roles: {
                    all: {
                        privileges: [],
                        dependencies: [
                            'app.appExample',
                        ],
                    },
                },
            },
        ];

        appAclService = new AppAclService({
            appRepository: {
                search: () => Promise.resolve([
                    {
                        name: 'appExample',
                    },
                ]),
            },
        });
    });

    it('getAppPermissions should return a app permission array', async () => {
        const data = await appAclService.getAppPermissions();

        expect(data).toEqual(appPermissionData);
    });
});
