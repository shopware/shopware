/**
 * @sw-package framework
 */
import type { InjectionKey } from 'vue';

/**
 * @private
 *
 * Carries the block names of the `sw-native-block-host` instances already mounted above the current one.
 *
 * A component that redeclares a block of the component it extends gets a wrapper on both templates, and
 * `{% parent %}` nests one inside the other. The inner host reads this list to stay inert instead of
 * rendering the same native extension twice.
 */
export default Symbol('nativeBlockHosts') as InjectionKey<string[]>;
