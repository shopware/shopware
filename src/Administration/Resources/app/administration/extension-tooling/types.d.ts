/**
 * @sw-package framework
 *
 * The extension tooling intentionally reads these declarations from the
 * installed Administration source. This keeps trunk checkouts and generated
 * entity schemas in sync without publishing a separate npm package.
 */

import './public-api';
import '../src/entity-schema-definition';
import '../src/html-shim';
