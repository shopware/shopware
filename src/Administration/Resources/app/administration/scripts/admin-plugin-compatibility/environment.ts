/**
 * @sw-package framework
 */
/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import { CI_GUARD_ENV_VARS } from './constants';

export function getCiGuardVariables(env: NodeJS.ProcessEnv): string[] {
    return CI_GUARD_ENV_VARS.filter((name) => Object.prototype.hasOwnProperty.call(env, name));
}
