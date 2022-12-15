/**
 * @package admin
 */

import type { FullState } from '../../core/factory/state.factory';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class AclService {
    state: FullState;

    constructor(state: FullState) {
        this.state = state;
    }

    isAdmin(): boolean {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
        return !!this.state.get('session').currentUser && !!this.state.get('session').currentUser.admin;
    }

    can(privilegeKey: string): boolean {
        if (this.isAdmin() || !privilegeKey) {
            return true;
        }


        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
        return (this.state.getters.userPrivileges as string[]).includes(privilegeKey);
    }

    hasAccessToRoute(path: string): boolean {
        const route = path.replace(/\./g, '/');
        if (route === '/sw/settings/index') {
            return this.hasActiveSettingModules();
        }

        if (!Shopware?.Application?.view?.root?.$router) {
            return true;
        }

        const router = Shopware.Application.view.root.$router;
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call
        const match = router.match(route) as { meta?: { privilege: string}};

        if (!match.meta) {
            return true;
        }

        return this.can(match.meta.privilege);
    }


    hasActiveSettingModules(): boolean {
        // @ts-expect-error
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-argument
        const groups = Object.values(this.state.get('settingsItems').settingsGroups) as [[{privilege?: string}]];

        let hasActive = false;

        groups.forEach((modules) => {
            modules.forEach((module) => {
                if (!module.privilege) {
                    hasActive = true;
                } else if (this.can(module.privilege)) {
                    hasActive = true;
                }
            });
        });

        return hasActive;
    }

    get privileges(): string[] {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
        return this.state.getters.userPrivileges as string[];
    }
}
