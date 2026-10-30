/**
 * @sw-package framework
 */
import oldFunction from './oldFunction';

/**
 * A module outside any component that uses the deprecated `oldFunction()`, for the usage site specs.
 *
 * @private
 */
export default function oldFunctionCaller(): void {
    oldFunction();
}
