/**
 * @package admin
 */

import filter from 'src/app/filter';

const createdAppFilter = filter();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function creatAppFilter() {
    // eslint-disable-next-line @typescript-eslint/no-unsafe-return
    return createdAppFilter;
}
