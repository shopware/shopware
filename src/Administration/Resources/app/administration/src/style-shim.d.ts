/**
 * @sw-package framework
 *
 * Stylesheets are resolved and bundled by Vite/Webpack, not by TypeScript.
 * Without this declaration, `import './foo.scss';` fails under
 * `noUncheckedSideEffectImports`, which TypeScript enables by default as of 6.0.
 */

declare module '*.scss';
