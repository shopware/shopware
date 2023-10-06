/**
 * @package admin
 */

declare module '*.html.twig' {
    const content: string;

    // eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
    export default content;
}

declare module '*.html' {
    const content: string;

    // eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
    export default content;
}
