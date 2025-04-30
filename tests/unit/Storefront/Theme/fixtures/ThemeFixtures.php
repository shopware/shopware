<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\fixtures;

/**
 * @internal
 */
class ThemeFixtures
{
    /**
     * @return array<string, mixed>
     */
    public static function getThemeConfig(string $faviconId, string $demostoreLogoId): array
    {
        return [
            'tabs' => [
                'default' => [
                    'labels' => '',
                    'blocks' => [
                        'themeColors' => [
                            'label' => 'themeColors',
                            'sections' => [
                                'default' => [
                                    'label' => '',
                                    'fields' => [
                                        'sw-color-brand-primary' => [
                                            'label' => [
                                                'en-GB' => 'Primary colour',
                                                'de-DE' => 'Primärfarbe',
                                            ],
                                            'helpText' => null,
                                            'type' => 'color',
                                            'custom' => null,
                                            'fullWidth' => null,
                                        ],
                                        'sw-color-brand-secondary' => [
                                            'label' => [
                                                'en-GB' => 'Secondary colour',
                                                'de-DE' => 'Sekundärfarbe',
                                            ],
                                            'helpText' => null,
                                            'type' => 'color',
                                            'custom' => null,
                                            'fullWidth' => null,
                                        ],
                                        'sw-border-color' => [
                                            'label' => [
                                                'en-GB' => 'Border',
                                                'de-DE' => 'Rahmen',
                                            ],
                                            'helpText' => null,
                                            'type' => 'color',
                                            'custom' => null,
                                            'fullWidth' => null,
                                        ],
                                        'sw-background-color' => [
                                            'label' => [
                                                'en-GB' => 'Background',
                                                'de-DE' => 'Hintergrund',
                                            ],
                                            'helpText' => null,
                                            'type' => 'color',
                                            'custom' => null,
                                            'fullWidth' => null,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'statusColors' => [
                            'label' => 'statusColors',
                            'sections' => [
                                'default' => [
                                    'label' => '',
                                    'fields' => [
                                        'sw-color-success' => [
                                            'label' => [
                                                'en-GB' => 'Success',
                                                'de-DE' => 'Erfolg',
                                            ],
                                            'helpText' => null,
                                            'type' => 'color',
                                            'custom' => null,
                                            'fullWidth' => null,
                                        ],
                                        'sw-color-info' => [
                                            'label' => [
                                                'en-GB' => 'Information',
                                                'de-DE' => 'Information',
                                            ],
                                            'helpText' => null,
                                            'type' => 'color',
                                            'custom' => null,
                                            'fullWidth' => null,
                                        ],
                                        'sw-color-warning' => [
                                            'label' => [
                                                'en-GB' => 'Notice',
                                                'de-DE' => 'Hinweis',
                                            ],
                                            'helpText' => null,
                                            'type' => 'color',
                                            'custom' => null,
                                            'fullWidth' => null,
                                        ],
                                        'sw-color-danger' => [
                                            'label' => [
                                                'en-GB' => 'Error',
                                                'de-DE' => 'Fehler',
                                            ],
                                            'helpText' => null,
                                            'type' => 'color',
                                            'custom' => null,
                                            'fullWidth' => null,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'typography' => [
                            'label' => 'typography',
                            'sections' => [
                                'default' => [
                                    'label' => '',
                                    'fields' => [
                                        'sw-font-family-base' => [
                                            'label' => [
                                                'en-GB' => 'Fonttype text',
                                                'de-DE' => 'Schriftart Text',
                                            ],
                                            'helpText' => null,
                                            'type' => 'fontFamily',
                                            'custom' => null,
                                            'fullWidth' => null,
                                        ],
                                        'sw-text-color' => [
                                            'label' => [
                                                'en-GB' => 'Text colour',
                                                'de-DE' => 'Textfarbe',
                                            ],
                                            'helpText' => null,
                                            'type' => 'color',
                                            'custom' => null,
                                            'fullWidth' => null,
                                        ],
                                        'sw-font-family-headline' => [
                                            'label' => [
                                                'en-GB' => 'Fonttype headline',
                                                'de-DE' => 'Schriftart Überschrift',
                                            ],
                                            'helpText' => null,
                                            'type' => 'fontFamily',
                                            'custom' => null,
                                            'fullWidth' => null,
                                        ],
                                        'sw-headline-color' => [
                                            'label' => [
                                                'en-GB' => 'Headline colour',
                                                'de-DE' => 'Überschriftfarbe',
                                            ],
                                            'helpText' => null,
                                            'type' => 'color',
                                            'custom' => null,
                                            'fullWidth' => null,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'eCommerce' => [
                            'label' => 'eCommerce',
                            'sections' => [
                                'default' => [
                                    'label' => '',
                                    'fields' => [
                                        'sw-color-price' => [
                                            'label' => [
                                                'en-GB' => 'Price',
                                                'de-DE' => 'Preis',
                                            ],
                                            'helpText' => null,
                                            'type' => 'color',
                                            'custom' => null,
                                            'fullWidth' => null,
                                        ],
                                        'sw-color-buy-button' => [
                                            'label' => [
                                                'en-GB' => 'Buy button',
                                                'de-DE' => 'Kaufen-Button',
                                            ],
                                            'helpText' => null,
                                            'type' => 'color',
                                            'custom' => null,
                                            'fullWidth' => null,
                                        ],
                                        'sw-color-buy-button-text' => [
                                            'label' => [
                                                'en-GB' => 'Buy button text',
                                                'de-DE' => 'Kaufen-Button Text',
                                            ],
                                            'helpText' => null,
                                            'type' => 'color',
                                            'custom' => null,
                                            'fullWidth' => null,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'media' => [
                            'label' => 'media',
                            'sections' => [
                                'default' => [
                                    'label' => '',
                                    'fields' => [
                                        'sw-logo-desktop' => [
                                            'label' => [
                                                'en-GB' => 'Desktop',
                                                'de-DE' => 'Desktop',
                                            ],
                                            'helpText' => [
                                                'en-GB' => 'Displayed on viewport sizes above 991px and as a fallback on smaller viewports, if no other logo is set.',
                                                'de-DE' => 'Wird bei Ansichten über 991px angezeigt und als Alternative bei kleineren Auflösungen, für die kein anderes Logo eingestellt ist.',
                                            ],
                                            'type' => 'media',
                                            'custom' => null,
                                            'fullWidth' => true,
                                        ],
                                        'sw-logo-tablet' => [
                                            'label' => [
                                                'en-GB' => 'Tablet',
                                                'de-DE' => 'Tablet',
                                            ],
                                            'helpText' => [
                                                'en-GB' => 'Displayed between a viewport of 767px to 991px',
                                                'de-DE' => 'Wird zwischen einem viewport von 767px bis 991px angezeigt',
                                            ],
                                            'type' => 'media',
                                            'custom' => null,
                                            'fullWidth' => true,
                                        ],
                                        'sw-logo-mobile' => [
                                            'label' => [
                                                'en-GB' => 'Mobile',
                                                'de-DE' => 'Mobil',
                                            ],
                                            'helpText' => [
                                                'en-GB' => 'Displayed up to a viewport of 767px',
                                                'de-DE' => 'Wird bis zu einem Viewport von 767px angezeigt',
                                            ],
                                            'type' => 'media',
                                            'custom' => null,
                                            'fullWidth' => true,
                                        ],
                                        'sw-logo-share' => [
                                            'label' => [
                                                'en-GB' => 'App & share icon',
                                                'de-DE' => 'App- & Share-Icon',
                                            ],
                                            'helpText' => null,
                                            'type' => 'media',
                                            'custom' => null,
                                            'fullWidth' => null,
                                        ],
                                        'sw-logo-favicon' => [
                                            'label' => [
                                                'en-GB' => 'Favicon',
                                                'de-DE' => 'Favicon',
                                            ],
                                            'helpText' => null,
                                            'type' => 'media',
                                            'custom' => null,
                                            'fullWidth' => null,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'default' => [
                            'label' => '',
                            'sections' => [
                                'default' => [
                                    'label' => '',
                                    'fields' => [
                                        'test' => [
                                            'label' => null,
                                            'helpText' => null,
                                            'type' => null,
                                            'custom' => null,
                                            'fullWidth' => null,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'blocks' => [
                'themeColors' => [
                    'label' => [
                        'en-GB' => 'Theme colours',
                        'de-DE' => 'Theme-Farben',
                    ],
                ],
                'typography' => [
                    'label' => [
                        'en-GB' => 'Typography',
                        'de-DE' => 'Typografie',
                    ],
                ],
                'media' => [
                    'label' => [
                        'en-GB' => 'Media',
                        'de-DE' => 'Medien',
                    ],
                ],
                'eCommerce' => [
                    'label' => [
                        'en-GB' => 'E-Commerce',
                        'de-DE' => 'E-Commerce',
                    ],
                ],
                'statusColors' => [
                    'label' => [
                        'en-GB' => 'Status messages',
                        'de-DE' => 'Status-Ausgaben',
                    ],
                ],
                'unordered' => [
                    'label' => [
                        'en-GB' => 'Misc',
                        'de-DE' => 'Sonstige',
                    ],
                ],
            ],
            'fields' => [
                'sw-color-brand-primary' => [
                    'name' => 'sw-color-brand-primary',
                    'label' => [
                        'en-GB' => 'Primary colour',
                        'de-DE' => 'Primärfarbe',
                    ],
                    'type' => 'color',
                    'value' => '#008490',
                    'editable' => true,
                    'block' => 'themeColors',
                    'section' => null,
                    'order' => 100,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [],
                    'helpText' => null,
                    'custom' => null,
                    'tab' => null,
                    'tabOrder' => null,
                    'scss' => null,
                    'fullWidth' => null,
                ],
                'sw-color-brand-secondary' => [
                    'name' => 'sw-color-brand-secondary',
                    'label' => [
                        'en-GB' => 'Secondary colour',
                        'de-DE' => 'Sekundärfarbe',
                    ],
                    'type' => 'color',
                    'value' => '#526e7f',
                    'editable' => true,
                    'block' => 'themeColors',
                    'section' => null,
                    'order' => 200,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [],
                    'helpText' => null,
                    'custom' => null,
                    'tab' => null,
                    'tabOrder' => null,
                    'scss' => null,
                    'fullWidth' => null,
                ],
                'sw-color-success' => [
                    'name' => 'sw-color-success',
                    'label' => [
                        'en-GB' => 'Success',
                        'de-DE' => 'Erfolg',
                    ],
                    'type' => 'color',
                    'value' => '#3cc261',
                    'editable' => true,
                    'block' => 'statusColors',
                    'section' => null,
                    'order' => 100,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [],
                    'helpText' => null,
                    'custom' => null,
                    'tab' => null,
                    'tabOrder' => null,
                    'scss' => null,
                    'fullWidth' => null,
                ],
                'sw-color-info' => [
                    'name' => 'sw-color-info',
                    'label' => [
                        'en-GB' => 'Information',
                        'de-DE' => 'Information',
                    ],
                    'type' => 'color',
                    'value' => '#26b6cf',
                    'editable' => true,
                    'block' => 'statusColors',
                    'section' => null,
                    'order' => 200,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [],
                    'helpText' => null,
                    'custom' => null,
                    'tab' => null,
                    'tabOrder' => null,
                    'scss' => null,
                    'fullWidth' => null,
                ],
                'sw-color-warning' => [
                    'name' => 'sw-color-warning',
                    'label' => [
                        'en-GB' => 'Notice',
                        'de-DE' => 'Hinweis',
                    ],
                    'type' => 'color',
                    'value' => '#ffbd5d',
                    'editable' => true,
                    'block' => 'statusColors',
                    'section' => null,
                    'order' => 300,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [],
                    'helpText' => null,
                    'custom' => null,
                    'tab' => null,
                    'tabOrder' => null,
                    'scss' => null,
                    'fullWidth' => null,
                ],
                'sw-color-danger' => [
                    'name' => 'sw-color-danger',
                    'label' => [
                        'en-GB' => 'Error',
                        'de-DE' => 'Fehler',
                    ],
                    'type' => 'color',
                    'value' => '#e52427',
                    'editable' => true,
                    'block' => 'statusColors',
                    'section' => null,
                    'order' => 400,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [],
                    'helpText' => null,
                    'custom' => null,
                    'tab' => null,
                    'tabOrder' => null,
                    'scss' => null,
                    'fullWidth' => null,
                ],
                'sw-text-color' => [
                    'name' => 'sw-text-color',
                    'label' => [
                        'en-GB' => 'Text colour',
                        'de-DE' => 'Textfarbe',
                    ],
                    'type' => 'color',
                    'value' => '#4a545b',
                    'editable' => true,
                    'block' => 'typography',
                    'section' => null,
                    'order' => 200,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [],
                    'helpText' => null,
                    'custom' => null,
                    'tab' => null,
                    'tabOrder' => null,
                    'scss' => null,
                    'fullWidth' => null,
                ],
                'sw-border-color' => [
                    'name' => 'sw-border-color',
                    'label' => [
                        'en-GB' => 'Border',
                        'de-DE' => 'Rahmen',
                    ],
                    'type' => 'color',
                    'value' => '#bcc1c7',
                    'editable' => true,
                    'block' => 'themeColors',
                    'section' => null,
                    'order' => 300,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [],
                    'helpText' => null,
                    'custom' => null,
                    'tab' => null,
                    'tabOrder' => null,
                    'scss' => null,
                    'fullWidth' => null,
                ],
                'sw-background-color' => [
                    'label' => [
                        'en-GB' => 'Background',
                        'de-DE' => 'Hintergrund',
                    ],
                    'type' => 'color',
                    'value' => '#fff',
                    'editable' => true,
                    'block' => 'themeColors',
                    'order' => 400,
                    'name' => 'sw-background-color',
                    'section' => null,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'helpText' => null,
                    'extensions' => [],
                    'custom' => null,
                    'tab' => null,
                    'tabOrder' => null,
                    'scss' => null,
                    'fullWidth' => null,
                ],
                'sw-color-price' => [
                    'name' => 'sw-color-price',
                    'label' => [
                        'en-GB' => 'Price',
                        'de-DE' => 'Preis',
                    ],
                    'type' => 'color',
                    'value' => '#4a545b',
                    'editable' => true,
                    'block' => 'eCommerce',
                    'section' => null,
                    'order' => 100,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [],
                    'helpText' => null,
                    'custom' => null,
                    'tab' => null,
                    'tabOrder' => null,
                    'scss' => null,
                    'fullWidth' => null,
                ],
                'sw-color-buy-button' => [
                    'name' => 'sw-color-buy-button',
                    'label' => [
                        'en-GB' => 'Buy button',
                        'de-DE' => 'Kaufen-Button',
                    ],
                    'type' => 'color',
                    'value' => '#008490',
                    'editable' => true,
                    'block' => 'eCommerce',
                    'section' => null,
                    'order' => 200,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [],
                    'helpText' => null,
                    'custom' => null,
                    'tab' => null,
                    'tabOrder' => null,
                    'scss' => null,
                    'fullWidth' => null,
                ],
                'sw-color-buy-button-text' => [
                    'label' => [
                        'en-GB' => 'Buy button text',
                        'de-DE' => 'Kaufen-Button Text',
                    ],
                    'type' => 'color',
                    'value' => '#fff',
                    'editable' => true,
                    'block' => 'eCommerce',
                    'order' => 300,
                    'name' => 'sw-color-buy-button-text',
                    'section' => null,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [],
                    'helpText' => null,
                    'custom' => null,
                    'tab' => null,
                    'tabOrder' => null,
                    'scss' => null,
                    'fullWidth' => null,
                ],
                'sw-font-family-base' => [
                    'name' => 'sw-font-family-base',
                    'label' => [
                        'en-GB' => 'Fonttype text',
                        'de-DE' => 'Schriftart Text',
                    ],
                    'type' => 'fontFamily',
                    'value' => '\'Inter\', sans-serif',
                    'editable' => true,
                    'block' => 'typography',
                    'section' => null,
                    'order' => 100,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [],
                    'helpText' => null,
                    'custom' => null,
                    'tab' => null,
                    'tabOrder' => null,
                    'scss' => null,
                    'fullWidth' => null,
                ],
                'sw-font-family-headline' => [
                    'name' => 'sw-font-family-headline',
                    'label' => [
                        'en-GB' => 'Fonttype headline',
                        'de-DE' => 'Schriftart Überschrift',
                    ],
                    'type' => 'fontFamily',
                    'value' => '\'Inter\', sans-serif',
                    'editable' => true,
                    'block' => 'typography',
                    'section' => null,
                    'order' => 300,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [],
                    'helpText' => null,
                    'custom' => null,
                    'tab' => null,
                    'tabOrder' => null,
                    'scss' => null,
                    'fullWidth' => null,
                ],
                'sw-headline-color' => [
                    'label' => [
                        'en-GB' => 'Headline colour',
                        'de-DE' => 'Überschriftfarbe',
                    ],
                    'type' => 'color',
                    'value' => '#4a545b',
                    'editable' => true,
                    'block' => 'typography',
                    'order' => 400,
                    'name' => 'sw-headline-color',
                    'section' => null,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [],
                    'helpText' => null,
                    'custom' => null,
                    'tab' => null,
                    'tabOrder' => null,
                    'scss' => null,
                    'fullWidth' => null,
                ],
                'sw-logo-desktop' => [
                    'label' => [
                        'en-GB' => 'Desktop',
                        'de-DE' => 'Desktop',
                    ],
                    'helpText' => [
                        'en-GB' => 'Displayed on viewport sizes above 991px and as a fallback on smaller viewports, if no other logo is set.',
                        'de-DE' => 'Wird bei Ansichten über 991px angezeigt und als Alternative bei kleineren Auflösungen, für die kein anderes Logo eingestellt ist.',
                    ],
                    'type' => 'media',
                    'value' => 'dist/assets/logo/demostore-logo.png',
                    'editable' => true,
                    'block' => 'media',
                    'order' => 100,
                    'name' => 'sw-logo-desktop',
                    'section' => null,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [],
                    'custom' => null,
                    'tab' => null,
                    'tabOrder' => null,
                    'scss' => null,
                    'fullWidth' => true,
                ],
                'sw-logo-tablet' => [
                    'label' => [
                        'en-GB' => 'Tablet',
                        'de-DE' => 'Tablet',
                    ],
                    'helpText' => [
                        'en-GB' => 'Displayed between a viewport of 767px to 991px',
                        'de-DE' => 'Wird zwischen einem viewport von 767px bis 991px angezeigt',
                    ],
                    'type' => 'media',
                    'value' => 'dist/assets/logo/demostore-logo.png',
                    'editable' => true,
                    'block' => 'media',
                    'order' => 200,
                    'name' => 'sw-logo-tablet',
                    'section' => null,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [],
                    'custom' => null,
                    'tab' => null,
                    'tabOrder' => null,
                    'scss' => null,
                    'fullWidth' => true,
                ],
                'sw-logo-mobile' => [
                    'label' => [
                        'en-GB' => 'Mobile',
                        'de-DE' => 'Mobil',
                    ],
                    'helpText' => [
                        'en-GB' => 'Displayed up to a viewport of 767px',
                        'de-DE' => 'Wird bis zu einem Viewport von 767px angezeigt',
                    ],
                    'type' => 'media',
                    'value' => 'dist/assets/logo/demostore-logo.png',
                    'editable' => true,
                    'block' => 'media',
                    'order' => 300,
                    'name' => 'sw-logo-mobile',
                    'section' => null,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [],
                    'custom' => null,
                    'tab' => null,
                    'tabOrder' => null,
                    'scss' => null,
                    'fullWidth' => true,
                ],
                'sw-logo-share' => [
                    'label' => [
                        'en-GB' => 'App & share icon',
                        'de-DE' => 'App- & Share-Icon',
                    ],
                    'type' => 'media',
                    'value' => '',
                    'editable' => true,
                    'block' => 'media',
                    'order' => 400,
                    'name' => 'sw-logo-share',
                    'section' => null,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [],
                    'helpText' => null,
                    'custom' => null,
                    'tab' => null,
                    'tabOrder' => null,
                    'scss' => null,
                    'fullWidth' => null,
                ],
                'sw-logo-favicon' => [
                    'label' => [
                        'en-GB' => 'Favicon',
                        'de-DE' => 'Favicon',
                    ],
                    'type' => 'media',
                    'value' => 'dist/assets/logo/favicon.png',
                    'editable' => true,
                    'block' => 'media',
                    'order' => 500,
                    'name' => 'sw-logo-favicon',
                    'section' => null,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [],
                    'helpText' => null,
                    'custom' => null,
                    'tab' => null,
                    'tabOrder' => null,
                    'scss' => null,
                    'fullWidth' => null,
                ],
            ],
            'currentFields' => [
                'sw-color-brand-primary' => [
                    'value' => '#008490',
                    'isInherited' => false,
                ],
                'sw-color-brand-secondary' => [
                    'value' => '#526e7f',
                    'isInherited' => false,
                ],
                'sw-border-color' => [
                    'value' => '#bcc1c7',
                    'isInherited' => false,
                ],
                'sw-background-color' => [
                    'value' => '#fff',
                    'isInherited' => false,
                ],
                'sw-color-success' => [
                    'value' => '#3cc261',
                    'isInherited' => false,
                ],
                'sw-color-info' => [
                    'value' => '#26b6cf',
                    'isInherited' => false,
                ],
                'sw-color-warning' => [
                    'value' => '#ffbd5d',
                    'isInherited' => false,
                ],
                'sw-color-danger' => [
                    'value' => '#e52427',
                    'isInherited' => false,
                ],
                'sw-font-family-base' => [
                    'value' => '\'Inter\', sans-serif',
                    'isInherited' => false,
                ],
                'sw-text-color' => [
                    'value' => '#4a545b',
                    'isInherited' => false,
                ],
                'sw-font-family-headline' => [
                    'value' => '\'Inter\', sans-serif',
                    'isInherited' => false,
                ],
                'sw-headline-color' => [
                    'value' => '#4a545b',
                    'isInherited' => false,
                ],
                'sw-color-price' => [
                    'value' => '#4a545b',
                    'isInherited' => false,
                ],
                'sw-color-buy-button' => [
                    'value' => '#008490',
                    'isInherited' => false,
                ],
                'sw-color-buy-button-text' => [
                    'value' => '#fff',
                    'isInherited' => false,
                ],
                'sw-logo-desktop' => [
                    'value' => $demostoreLogoId,
                    'isInherited' => false,
                ],
                'sw-logo-tablet' => [
                    'value' => $demostoreLogoId,
                    'isInherited' => false,
                ],
                'sw-logo-mobile' => [
                    'value' => $demostoreLogoId,
                    'isInherited' => false,
                ],
                'sw-logo-share' => [
                    'value' => '',
                    'isInherited' => false,
                ],
                'sw-logo-favicon' => [
                    'value' => $faviconId,
                    'isInherited' => false,
                ],
            ],
            'baseThemeFields' => [
                'sw-color-brand-primary' => [
                    'value' => '#008490',
                    'isInherited' => false,
                ],
                'sw-color-brand-secondary' => [
                    'value' => '#526e7f',
                    'isInherited' => false,
                ],
                'sw-border-color' => [
                    'value' => '#bcc1c7',
                    'isInherited' => false,
                ],
                'sw-background-color' => [
                    'value' => '#fff',
                    'isInherited' => false,
                ],
                'sw-color-success' => [
                    'value' => '#3cc261',
                    'isInherited' => false,
                ],
                'sw-color-info' => [
                    'value' => '#26b6cf',
                    'isInherited' => false,
                ],
                'sw-color-warning' => [
                    'value' => '#ffbd5d',
                    'isInherited' => false,
                ],
                'sw-color-danger' => [
                    'value' => '#e52427',
                    'isInherited' => false,
                ],
                'sw-font-family-base' => [
                    'value' => '\'Inter\', sans-serif',
                    'isInherited' => false,
                ],
                'sw-text-color' => [
                    'value' => '#4a545b',
                    'isInherited' => false,
                ],
                'sw-font-family-headline' => [
                    'value' => '\'Inter\', sans-serif',
                    'isInherited' => false,
                ],
                'sw-headline-color' => [
                    'value' => '#4a545b',
                    'isInherited' => false,
                ],
                'sw-color-price' => [
                    'value' => '#4a545b',
                    'isInherited' => false,
                ],
                'sw-color-buy-button' => [
                    'value' => '#008490',
                    'isInherited' => false,
                ],
                'sw-color-buy-button-text' => [
                    'value' => '#fff',
                    'isInherited' => false,
                ],
                'sw-logo-desktop' => [
                    'value' => $demostoreLogoId,
                    'isInherited' => false,
                ],
                'sw-logo-tablet' => [
                    'value' => $demostoreLogoId,
                    'isInherited' => false,
                ],
                'sw-logo-mobile' => [
                    'value' => $demostoreLogoId,
                    'isInherited' => false,
                ],
                'sw-logo-share' => [
                    'value' => null,
                    'isInherited' => false,
                ],
                'sw-logo-favicon' => [
                    'value' => $faviconId,
                    'isInherited' => false,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getThemeJsonConfig(): array
    {
        return [
            'name' => 'test',
            'fields' => [
                'sw-color-brand-primary' => [
                    'type' => 'color',
                    'value' => '#008490',
                    'editable' => true,
                    'block' => 'themeColors',
                    'order' => 100,
                ],

                'sw-color-brand-secondary' => [
                    'type' => 'color',
                    'value' => '#526e7f',
                    'editable' => true,
                    'block' => 'themeColors',
                    'order' => 200,
                ],

                'sw-border-color' => [
                    'type' => 'color',
                    'value' => '#bcc1c7',
                    'editable' => true,
                    'block' => 'themeColors',
                    'order' => 300,
                ],

                'sw-background-color' => [
                    'type' => 'color',
                    'value' => '#fff',
                    'editable' => true,
                    'block' => 'themeColors',
                    'order' => 400,
                ],

                'sw-color-success' => [
                    'type' => 'color',
                    'value' => '#3cc261',
                    'editable' => true,
                    'block' => 'statusColors',
                    'order' => 100,
                ],

                'sw-color-info' => [
                    'type' => 'color',
                    'value' => '#26b6cf',
                    'editable' => true,
                    'block' => 'statusColors',
                    'order' => 200,
                ],

                'sw-color-warning' => [
                    'type' => 'color',
                    'value' => '#ffbd5d',
                    'editable' => true,
                    'block' => 'statusColors',
                    'order' => 300,
                ],

                'sw-color-danger' => [
                    'type' => 'color',
                    'value' => '#e52427',
                    'editable' => true,
                    'block' => 'statusColors',
                    'order' => 400,
                ],

                'sw-font-family-base' => [
                    'type' => 'fontFamily',
                    'value' => '\'Inter\', sans-serif',
                    'editable' => true,
                    'block' => 'typography',
                    'order' => 100,
                ],

                'sw-text-color' => [
                    'type' => 'color',
                    'value' => '#4a545b',
                    'editable' => true,
                    'block' => 'typography',
                    'order' => 200,
                ],

                'sw-font-family-headline' => [
                    'type' => 'fontFamily',
                    'value' => '\'Inter\', sans-serif',
                    'editable' => true,
                    'block' => 'typography',
                    'order' => 300,
                ],

                'sw-headline-color' => [
                    'type' => 'color',
                    'value' => '#4a545b',
                    'editable' => true,
                    'block' => 'typography',
                    'order' => 400,
                ],

                'sw-color-price' => [
                    'type' => 'color',
                    'value' => '#4a545b',
                    'editable' => true,
                    'block' => 'eCommerce',
                    'order' => 100,
                ],

                'sw-color-buy-button' => [
                    'type' => 'color',
                    'value' => '#008490',
                    'editable' => true,
                    'block' => 'eCommerce',
                    'order' => 200,
                ],

                'sw-color-buy-button-text' => [
                    'type' => 'color',
                    'value' => '#fff',
                    'editable' => true,
                    'block' => 'eCommerce',
                    'order' => 300,
                ],

                'sw-logo-desktop' => [
                    'type' => 'media',
                    'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
                    'editable' => true,
                    'block' => 'media',
                    'order' => 100,
                    'fullWidth' => true,
                ],

                'sw-logo-tablet' => [
                    'type' => 'media',
                    'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
                    'editable' => true,
                    'block' => 'media',
                    'order' => 200,
                    'fullWidth' => true,
                ],

                'sw-logo-mobile' => [
                    'type' => 'media',
                    'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
                    'editable' => true,
                    'block' => 'media',
                    'order' => 300,
                    'fullWidth' => true,
                ],

                'sw-logo-share' => [
                    'type' => 'media',
                    'value' => '',
                    'editable' => true,
                    'block' => 'media',
                    'order' => 400,
                ],

                'sw-logo-favicon' => [
                    'type' => 'media',
                    'value' => 'app/storefront/dist/assets/logo/favicon.png',
                    'editable' => true,
                    'block' => 'media',
                    'order' => 500,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedBlock1(): array
    {
        return [
            'themeColors' => [
                'label' => [
                    'en-GB' => 'Theme colours',
                    'de-DE' => 'Theme-Farben',
                ],
            ],
            'typography' => [
                'label' => [
                    'en-GB' => 'Typography',
                    'de-DE' => 'Typografie',
                ],
            ],
            'eCommerce' => [
                'label' => [
                    'en-GB' => 'E-Commerce',
                    'de-DE' => 'E-Commerce',
                ],
            ],
            'statusColors' => [
                'label' => [
                    'en-GB' => 'Status messages',
                    'de-DE' => 'Status-Ausgaben',
                ],
            ],
            'media' => [
                'label' => [
                    'en-GB' => 'Media',
                    'de-DE' => 'Medien',
                ],
            ],
            'unordered' => [
                'label' => [
                    'en-GB' => 'Misc',
                    'de-DE' => 'Sonstige',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedFields1(): array
    {
        return [
            'sw-color-brand-primary' => [
                'extensions' => [
                ],
                'name' => 'sw-color-brand-primary',
                'label' => 'sw-color-brand-primary',
                'helpText' => null,
                'type' => 'color',
                'value' => '#008490',
                'editable' => true,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-brand-secondary' => [
                'extensions' => [
                ],
                'name' => 'sw-color-brand-secondary',
                'label' => 'sw-color-brand-secondary',
                'helpText' => null,
                'type' => 'color',
                'value' => '#526e7f',
                'editable' => true,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-border-color' => [
                'extensions' => [
                ],
                'name' => 'sw-border-color',
                'label' => 'sw-border-color',
                'helpText' => null,
                'type' => 'color',
                'value' => '#bcc1c7',
                'editable' => true,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-background-color' => [
                'extensions' => [
                ],
                'name' => 'sw-background-color',
                'label' => 'sw-background-color',
                'helpText' => null,
                'type' => 'color',
                'value' => '#fff',
                'editable' => true,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-success' => [
                'extensions' => [
                ],
                'name' => 'sw-color-success',
                'label' => 'sw-color-success',
                'helpText' => null,
                'type' => 'color',
                'value' => '#3cc261',
                'editable' => true,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-info' => [
                'extensions' => [
                ],
                'name' => 'sw-color-info',
                'label' => 'sw-color-info',
                'helpText' => null,
                'type' => 'color',
                'value' => '#26b6cf',
                'editable' => true,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-warning' => [
                'extensions' => [
                ],
                'name' => 'sw-color-warning',
                'label' => 'sw-color-warning',
                'helpText' => null,
                'type' => 'color',
                'value' => '#ffbd5d',
                'editable' => true,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-danger' => [
                'extensions' => [
                ],
                'name' => 'sw-color-danger',
                'label' => 'sw-color-danger',
                'helpText' => null,
                'type' => 'color',
                'value' => '#e52427',
                'editable' => true,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-font-family-base' => [
                'extensions' => [
                ],
                'name' => 'sw-font-family-base',
                'label' => 'sw-font-family-base',
                'helpText' => null,
                'type' => 'fontFamily',
                'value' => '\'Inter\', sans-serif',
                'editable' => true,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-text-color' => [
                'extensions' => [
                ],
                'name' => 'sw-text-color',
                'label' => 'sw-text-color',
                'helpText' => null,
                'type' => 'color',
                'value' => '#4a545b',
                'editable' => true,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-font-family-headline' => [
                'extensions' => [
                ],
                'name' => 'sw-font-family-headline',
                'label' => 'sw-font-family-headline',
                'helpText' => null,
                'type' => 'fontFamily',
                'value' => '\'Inter\', sans-serif',
                'editable' => true,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-headline-color' => [
                'extensions' => [
                ],
                'name' => 'sw-headline-color',
                'label' => 'sw-headline-color',
                'helpText' => null,
                'type' => 'color',
                'value' => '#4a545b',
                'editable' => true,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-price' => [
                'extensions' => [
                ],
                'name' => 'sw-color-price',
                'label' => 'sw-color-price',
                'helpText' => null,
                'type' => 'color',
                'value' => '#4a545b',
                'editable' => true,
                'block' => 'eCommerce',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-buy-button' => [
                'extensions' => [
                ],
                'name' => 'sw-color-buy-button',
                'label' => 'sw-color-buy-button',
                'helpText' => null,
                'type' => 'color',
                'value' => '#008490',
                'editable' => true,
                'block' => 'eCommerce',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-buy-button-text' => [
                'extensions' => [
                ],
                'name' => 'sw-color-buy-button-text',
                'label' => 'sw-color-buy-button-text',
                'helpText' => null,
                'type' => 'color',
                'value' => '#fff',
                'editable' => true,
                'block' => 'eCommerce',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-logo-desktop' => [
                'extensions' => [
                ],
                'name' => 'sw-logo-desktop',
                'label' => 'sw-logo-desktop',
                'helpText' => null,
                'type' => 'media',
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
                'editable' => true,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => true,
            ],
            'sw-logo-tablet' => [
                'extensions' => [
                ],
                'name' => 'sw-logo-tablet',
                'label' => 'sw-logo-tablet',
                'helpText' => null,
                'type' => 'media',
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
                'editable' => true,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => true,
            ],
            'sw-logo-mobile' => [
                'extensions' => [
                ],
                'name' => 'sw-logo-mobile',
                'label' => 'sw-logo-mobile',
                'helpText' => null,
                'type' => 'media',
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
                'editable' => true,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => true,
            ],
            'sw-logo-share' => [
                'extensions' => [
                ],
                'name' => 'sw-logo-share',
                'label' => 'sw-logo-share',
                'helpText' => null,
                'type' => 'media',
                'value' => null,
                'editable' => true,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-logo-favicon' => [
                'extensions' => [
                ],
                'name' => 'sw-logo-favicon',
                'label' => 'sw-logo-favicon',
                'helpText' => null,
                'type' => 'media',
                'value' => 'app/storefront/dist/assets/logo/favicon.png',
                'editable' => true,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 500,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'test' => [
                'extensions' => [
                ],
                'name' => 'test',
                'label' => 'test',
                'helpText' => null,
                'value' => [
                    0 => 'no_test',
                ],
                'editable' => null,
                'block' => null,
                'section' => null,
                'tab' => null,
                'order' => null,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function getExtractedConfigInheritance(): array
    {
        return [
            0 => '@ParentTheme',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedConfig1(): array
    {
        return [
            'name' => 'test',
            'fields' => [
                'sw-color-brand-primary' => [
                    'type' => 'color',
                    'value' => '#008490',
                    'editable' => true,
                    'block' => 'themeColors',
                    'order' => 100,
                ],
                'sw-color-brand-secondary' => [
                    'type' => 'color',
                    'value' => '#526e7f',
                    'editable' => true,
                    'block' => 'themeColors',
                    'order' => 200,
                ],
                'sw-border-color' => [
                    'type' => 'color',
                    'value' => '#bcc1c7',
                    'editable' => true,
                    'block' => 'themeColors',
                    'order' => 300,
                ],
                'sw-background-color' => [
                    'type' => 'color',
                    'value' => '#fff',
                    'editable' => true,
                    'block' => 'themeColors',
                    'order' => 400,
                ],
                'sw-color-success' => [
                    'type' => 'color',
                    'value' => '#3cc261',
                    'editable' => true,
                    'block' => 'statusColors',
                    'order' => 100,
                ],
                'sw-color-info' => [
                    'type' => 'color',
                    'value' => '#26b6cf',
                    'editable' => true,
                    'block' => 'statusColors',
                    'order' => 200,
                ],
                'sw-color-warning' => [
                    'type' => 'color',
                    'value' => '#ffbd5d',
                    'editable' => true,
                    'block' => 'statusColors',
                    'order' => 300,
                ],
                'sw-color-danger' => [
                    'type' => 'color',
                    'value' => '#e52427',
                    'editable' => true,
                    'block' => 'statusColors',
                    'order' => 400,
                ],
                'sw-font-family-base' => [
                    'type' => 'fontFamily',
                    'value' => '\'Inter\', sans-serif',
                    'editable' => true,
                    'block' => 'typography',
                    'order' => 100,
                ],
                'sw-text-color' => [
                    'type' => 'color',
                    'value' => '#4a545b',
                    'editable' => true,
                    'block' => 'typography',
                    'order' => 200,
                ],
                'sw-font-family-headline' => [
                    'type' => 'fontFamily',
                    'value' => '\'Inter\', sans-serif',
                    'editable' => true,
                    'block' => 'typography',
                    'order' => 300,
                ],
                'sw-headline-color' => [
                    'type' => 'color',
                    'value' => '#4a545b',
                    'editable' => true,
                    'block' => 'typography',
                    'order' => 400,
                ],
                'sw-color-price' => [
                    'type' => 'color',
                    'value' => '#4a545b',
                    'editable' => true,
                    'block' => 'eCommerce',
                    'order' => 100,
                ],
                'sw-color-buy-button' => [
                    'type' => 'color',
                    'value' => '#008490',
                    'editable' => true,
                    'block' => 'eCommerce',
                    'order' => 200,
                ],
                'sw-color-buy-button-text' => [
                    'type' => 'color',
                    'value' => '#fff',
                    'editable' => true,
                    'block' => 'eCommerce',
                    'order' => 300,
                ],
                'sw-logo-desktop' => [
                    'type' => 'media',
                    'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
                    'editable' => true,
                    'block' => 'media',
                    'order' => 100,
                    'fullWidth' => true,
                ],
                'sw-logo-tablet' => [
                    'type' => 'media',
                    'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
                    'editable' => true,
                    'block' => 'media',
                    'order' => 200,
                    'fullWidth' => true,
                ],
                'sw-logo-mobile' => [
                    'type' => 'media',
                    'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
                    'editable' => true,
                    'block' => 'media',
                    'order' => 300,
                    'fullWidth' => true,
                ],
                'sw-logo-share' => [
                    'type' => 'media',
                    'value' => null,
                    'editable' => true,
                    'block' => 'media',
                    'order' => 400,
                ],
                'sw-logo-favicon' => [
                    'type' => 'media',
                    'value' => 'app/storefront/dist/assets/logo/favicon.png',
                    'editable' => true,
                    'block' => 'media',
                    'order' => 500,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedCurrentFields1(): array
    {
        return [
            'sw-color-brand-primary' => [
                'isInherited' => null,
                'value' => '#008490',
            ],
            'sw-color-brand-secondary' => [
                'isInherited' => null,
                'value' => '#526e7f',
            ],
            'sw-border-color' => [
                'isInherited' => null,
                'value' => '#bcc1c7',
            ],
            'sw-background-color' => [
                'isInherited' => null,
                'value' => '#fff',
            ],
            'sw-color-success' => [
                'isInherited' => null,
                'value' => '#3cc261',
            ],
            'sw-color-info' => [
                'isInherited' => null,
                'value' => '#26b6cf',
            ],
            'sw-color-warning' => [
                'isInherited' => null,
                'value' => '#ffbd5d',
            ],
            'sw-color-danger' => [
                'isInherited' => null,
                'value' => '#e52427',
            ],
            'sw-font-family-base' => [
                'isInherited' => null,
                'value' => '\'Inter\', sans-serif',
            ],
            'sw-text-color' => [
                'isInherited' => null,
                'value' => '#4a545b',
            ],
            'sw-font-family-headline' => [
                'isInherited' => null,
                'value' => '\'Inter\', sans-serif',
            ],
            'sw-headline-color' => [
                'isInherited' => null,
                'value' => '#4a545b',
            ],
            'sw-color-price' => [
                'isInherited' => null,
                'value' => '#4a545b',
            ],
            'sw-color-buy-button' => [
                'isInherited' => null,
                'value' => '#008490',
            ],
            'sw-color-buy-button-text' => [
                'isInherited' => null,
                'value' => '#fff',
            ],
            'sw-logo-desktop' => [
                'isInherited' => null,
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
            ],
            'sw-logo-tablet' => [
                'isInherited' => null,
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
            ],
            'sw-logo-mobile' => [
                'isInherited' => null,
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
            ],
            'sw-logo-share' => [
                'isInherited' => null,
                'value' => null,
            ],
            'sw-logo-favicon' => [
                'isInherited' => null,
                'value' => 'app/storefront/dist/assets/logo/favicon.png',
            ],
            'test' => [
                'isInherited' => null,
                'value' => [
                    0 => 'no_test',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedBaseThemeFields1(): array
    {
        return [
            'sw-color-brand-primary' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-color-brand-secondary' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-border-color' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-background-color' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-color-success' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-color-info' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-color-warning' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-color-danger' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-font-family-base' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-text-color' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-font-family-headline' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-headline-color' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-color-price' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-color-buy-button' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-color-buy-button-text' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-logo-desktop' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-logo-tablet' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-logo-mobile' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-logo-share' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-logo-favicon' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'test' => [
                'isInherited' => 1,
                'value' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedFieldsSub1(): array
    {
        return [
            'sw-color-brand-primary' => [
                'extensions' => [
                ],
                'name' => 'sw-color-brand-primary',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#008490',
                'editable' => true,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-brand-secondary' => [
                'extensions' => [
                ],
                'name' => 'sw-color-brand-secondary',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#526e7f',
                'editable' => true,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-border-color' => [
                'extensions' => [
                ],
                'name' => 'sw-border-color',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#bcc1c7',
                'editable' => 1,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-background-color' => [
                'extensions' => [
                ],
                'name' => 'sw-background-color',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#fff',
                'editable' => 1,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-success' => [
                'extensions' => [
                ],
                'name' => 'sw-color-success',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#3cc261',
                'editable' => 1,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-info' => [
                'extensions' => [
                ],
                'name' => 'sw-color-info',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#26b6cf',
                'editable' => 1,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-warning' => [
                'extensions' => [
                ],
                'name' => 'sw-color-warning',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#ffbd5d',
                'editable' => 1,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-danger' => [
                'extensions' => [
                ],
                'name' => 'sw-color-danger',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#e52427',
                'editable' => 1,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-font-family-base' => [
                'extensions' => [
                ],
                'name' => 'sw-font-family-base',
                'label' => null,
                'helpText' => null,
                'type' => 'fontFamily',
                'value' => '\'Inter\', sans-serif',
                'editable' => 1,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-text-color' => [
                'extensions' => [
                ],
                'name' => 'sw-text-color',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#4a545b',
                'editable' => 1,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-font-family-headline' => [
                'extensions' => [
                ],
                'name' => 'sw-font-family-headline',
                'label' => null,
                'helpText' => null,
                'type' => 'fontFamily',
                'value' => '\'Inter\', sans-serif',
                'editable' => 1,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-headline-color' => [
                'extensions' => [
                ],
                'name' => 'sw-headline-color',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#4a545b',
                'editable' => 1,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-price' => [
                'extensions' => [
                ],
                'name' => 'sw-color-price',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#4a545b',
                'editable' => 1,
                'block' => 'eCommerce',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-buy-button' => [
                'extensions' => [
                ],
                'name' => 'sw-color-buy-button',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#008490',
                'editable' => 1,
                'block' => 'eCommerce',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-buy-button-text' => [
                'extensions' => [
                ],
                'name' => 'sw-color-buy-button-text',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#fff',
                'editable' => 1,
                'block' => 'eCommerce',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-logo-desktop' => [
                'extensions' => [
                ],
                'name' => 'sw-logo-desktop',
                'label' => null,
                'helpText' => null,
                'type' => 'media',
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
                'editable' => 1,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => true,
            ],
            'sw-logo-tablet' => [
                'extensions' => [
                ],
                'name' => 'sw-logo-tablet',
                'label' => null,
                'helpText' => null,
                'type' => 'media',
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
                'editable' => 1,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => true,
            ],
            'sw-logo-mobile' => [
                'extensions' => [
                ],
                'name' => 'sw-logo-mobile',
                'label' => null,
                'helpText' => null,
                'type' => 'media',
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
                'editable' => 1,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => true,
            ],
            'sw-logo-share' => [
                'extensions' => [
                ],
                'name' => 'sw-logo-share',
                'label' => null,
                'helpText' => null,
                'type' => 'media',
                'value' => null,
                'editable' => 1,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-logo-favicon' => [
                'extensions' => [
                ],
                'name' => 'sw-logo-favicon',
                'label' => null,
                'helpText' => null,
                'type' => 'media',
                'value' => 'app/storefront/dist/assets/logo/favicon.png',
                'editable' => 1,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 500,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedConfig2(): array
    {
        return [
            'name' => 'test',
            'fields' => [
                'sw-color-brand-primary' => [
                    'type' => 'color',
                    'value' => '#008490',
                    'editable' => 1,
                    'block' => 'themeColors',
                    'order' => 100,
                ],
                'sw-color-brand-secondary' => [
                    'type' => 'color',
                    'value' => '#526e7f',
                    'editable' => 1,
                    'block' => 'themeColors',
                    'order' => 200,
                ],
                'sw-border-color' => [
                    'type' => 'color',
                    'value' => '#bcc1c7',
                    'editable' => 1,
                    'block' => 'themeColors',
                    'order' => 300,
                ],
                'sw-background-color' => [
                    'label' => [
                        'en-GB' => 'Background',
                        'de-DE' => 'Hintergrund',
                    ],
                    'type' => 'color',
                    'value' => '#fff',
                    'editable' => 1,
                    'block' => 'themeColors',
                    'order' => 400,
                ],
                'sw-color-success' => [
                    'label' => [
                        'en-GB' => 'Success',
                        'de-DE' => 'Erfolg',
                    ],
                    'type' => 'color',
                    'value' => '#3cc261',
                    'editable' => 1,
                    'block' => 'statusColors',
                    'order' => 100,
                ],
                'sw-color-info' => [
                    'label' => [
                        'en-GB' => 'Information',
                        'de-DE' => 'Information',
                    ],
                    'type' => 'color',
                    'value' => '#26b6cf',
                    'editable' => 1,
                    'block' => 'statusColors',
                    'order' => 200,
                ],
                'sw-color-warning' => [
                    'label' => [
                        'en-GB' => 'Notice',
                        'de-DE' => 'Hinweis',
                    ],
                    'type' => 'color',
                    'value' => '#ffbd5d',
                    'editable' => 1,
                    'block' => 'statusColors',
                    'order' => 300,
                ],
                'sw-color-danger' => [
                    'label' => [
                        'en-GB' => 'Error',
                        'de-DE' => 'Fehler',
                    ],
                    'type' => 'color',
                    'value' => '#e52427',
                    'editable' => 1,
                    'block' => 'statusColors',
                    'order' => 400,
                ],
                'sw-font-family-base' => [
                    'label' => [
                        'en-GB' => 'Fonttype text',
                        'de-DE' => 'Schriftart Text',
                    ],
                    'type' => 'fontFamily',
                    'value' => '\'Inter\', sans-serif',
                    'editable' => 1,
                    'block' => 'typography',
                    'order' => 100,
                ],
                'sw-text-color' => [
                    'label' => [
                        'en-GB' => 'Text colour',
                        'de-DE' => 'Textfarbe',
                    ],
                    'type' => 'color',
                    'value' => '#4a545b',
                    'editable' => 1,
                    'block' => 'typography',
                    'order' => 200,
                ],
                'sw-font-family-headline' => [
                    'label' => [
                        'en-GB' => 'Fonttype headline',
                        'de-DE' => 'Schriftart Überschrift',
                    ],
                    'type' => 'fontFamily',
                    'value' => '\'Inter\', sans-serif',
                    'editable' => 1,
                    'block' => 'typography',
                    'order' => 300,
                ],
                'sw-headline-color' => [
                    'label' => [
                        'en-GB' => 'Headline colour',
                        'de-DE' => 'Überschriftfarbe',
                    ],
                    'type' => 'color',
                    'value' => '#4a545b',
                    'editable' => 1,
                    'block' => 'typography',
                    'order' => 400,
                ],
                'sw-color-price' => [
                    'label' => [
                        'en-GB' => 'Price',
                        'de-DE' => 'Preis',
                    ],
                    'type' => 'color',
                    'value' => '#4a545b',
                    'editable' => 1,
                    'block' => 'eCommerce',
                    'order' => 100,
                ],
                'sw-color-buy-button' => [
                    'label' => [
                        'en-GB' => 'Buy button',
                        'de-DE' => 'Kaufen-Button',
                    ],
                    'type' => 'color',
                    'value' => '#008490',
                    'editable' => 1,
                    'block' => 'eCommerce',
                    'order' => 200,
                ],
                'sw-color-buy-button-text' => [
                    'label' => [
                        'en-GB' => 'Buy button text',
                        'de-DE' => 'Kaufen-Button Text',
                    ],
                    'type' => 'color',
                    'value' => '#fff',
                    'editable' => 1,
                    'block' => 'eCommerce',
                    'order' => 300,
                ],
                'sw-logo-desktop' => [
                    'label' => [
                        'en-GB' => 'Desktop',
                        'de-DE' => 'Desktop',
                    ],
                    'helpText' => [
                        'en-GB' => 'Displayed on viewport sizes above 991px and as a fallback on smaller viewports, if no other logo is set.',
                        'de-DE' => 'Wird bei Ansichten über 991px angezeigt und als Alternative bei kleineren Auflösungen, für die kein anderes Logo eingestellt ist.',
                    ],
                    'type' => 'media',
                    'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
                    'editable' => 1,
                    'block' => 'media',
                    'order' => 100,
                    'fullWidth' => true,
                ],
                'sw-logo-tablet' => [
                    'label' => [
                        'en-GB' => 'Tablet',
                        'de-DE' => 'Tablet',
                    ],
                    'helpText' => [
                        'en-GB' => 'Displayed between a viewport of 767px to 991px',
                        'de-DE' => 'Wird zwischen einem viewport von 767px bis 991px angezeigt',
                    ],
                    'type' => 'media',
                    'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
                    'editable' => 1,
                    'block' => 'media',
                    'order' => 200,
                    'fullWidth' => true,
                ],
                'sw-logo-mobile' => [
                    'label' => [
                        'en-GB' => 'Mobile',
                        'de-DE' => 'Mobil',
                    ],
                    'helpText' => [
                        'en-GB' => 'Displayed up to a viewport of 767px',
                        'de-DE' => 'Wird bis zu einem Viewport von 767px angezeigt',
                    ],
                    'type' => 'media',
                    'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
                    'editable' => 1,
                    'block' => 'media',
                    'order' => 300,
                    'fullWidth' => true,
                ],
                'sw-logo-share' => [
                    'label' => [
                        'en-GB' => 'App & share icon',
                        'de-DE' => 'App- & Share-Icon',
                    ],
                    'type' => 'media',
                    'value' => null,
                    'editable' => 1,
                    'block' => 'media',
                    'order' => 400,
                ],
                'sw-logo-favicon' => [
                    'label' => [
                        'en-GB' => 'Favicon',
                        'de-DE' => 'Favicon',
                    ],
                    'type' => 'media',
                    'value' => 'app/storefront/dist/assets/logo/favicon.png',
                    'editable' => 1,
                    'block' => 'media',
                    'order' => 500,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedTabsSub1(): array
    {
        return [
            'themeColors' => [
                'label' => 'themeColors',
                'labelSnippetKey' => 'sw-theme.test.default.themeColors.label',
                'sections' => [
                    'default' => [
                        'label' => null,
                        'labelSnippetKey' => 'sw-theme.test.default.themeColors.default.label',
                        'fields' => [
                            'sw-color-brand-primary' => [
                                'label' => [
                                    'en-GB' => 'Primary colour',
                                    'de-DE' => 'Primärfarbe',
                                ],
                                'labelSnippetKey' => 'sw-theme.test.default.themeColors.default.sw-color-brand-primary.label',
                                'helpTextSnippetKey' => 'sw-theme.test.default.themeColors.default.sw-color-brand-primary.helpText',
                                'helpText' => null,
                                'type' => 'color',
                                'custom' => null,
                                'fullWidth' => null,
                            ],
                            'sw-color-brand-secondary' => [
                                'label' => [
                                    'en-GB' => 'Secondary colour',
                                    'de-DE' => 'Sekundärfarbe',
                                ],
                                'labelSnippetKey' => 'sw-theme.test.default.themeColors.default.sw-color-brand-secondary.label',
                                'helpTextSnippetKey' => 'sw-theme.test.default.themeColors.default.sw-color-brand-secondary.helpText',
                                'helpText' => null,
                                'type' => 'color',
                                'custom' => null,
                                'fullWidth' => null,
                            ],
                            'sw-border-color' => [
                                'label' => [
                                    'en-GB' => 'Border',
                                    'de-DE' => 'Rahmen',
                                ],
                                'labelSnippetKey' => 'sw-theme.test.default.themeColors.default.sw-border-color.label',
                                'helpTextSnippetKey' => 'sw-theme.test.default.themeColors.default.sw-border-color.helpText',
                                'helpText' => null,
                                'type' => 'color',
                                'custom' => null,
                                'fullWidth' => null,
                            ],
                            'sw-background-color' => [
                                'label' => [
                                    'en-GB' => 'Background',
                                    'de-DE' => 'Hintergrund',
                                ],
                                'labelSnippetKey' => 'sw-theme.test.default.themeColors.default.sw-background-color.label',
                                'helpTextSnippetKey' => 'sw-theme.test.default.themeColors.default.sw-background-color.helpText',
                                'helpText' => null,
                                'type' => 'color',
                                'custom' => null,
                                'fullWidth' => null,
                            ],
                        ],
                    ],
                ],
            ],
            'statusColors' => [
                'label' => 'statusColors',
                'labelSnippetKey' => 'sw-theme.test.default.statusColors.label',
                'sections' => [
                    'default' => [
                        'label' => null,
                        'labelSnippetKey' => 'sw-theme.test.default.statusColors.default.label',
                        'fields' => [
                            'sw-color-success' => [
                                'label' => null,
                                'labelSnippetKey' => 'sw-theme.test.default.statusColors.default.sw-color-success.label',
                                'helpText' => null,
                                'helpTextSnippetKey' => 'sw-theme.test.default.statusColors.default.sw-color-success.helpText',
                                'type' => 'color',
                                'custom' => null,
                                'fullWidth' => null,
                            ],
                            'sw-color-info' => [
                                'label' => null,
                                'labelSnippetKey' => 'sw-theme.test.default.statusColors.default.sw-color-info.label',
                                'helpTextSnippetKey' => 'sw-theme.test.default.statusColors.default.sw-color-info.helpText',
                                'helpText' => null,
                                'type' => 'color',
                                'custom' => null,
                                'fullWidth' => null,
                            ],
                            'sw-color-warning' => [
                                'label' => null,
                                'labelSnippetKey' => 'sw-theme.test.default.statusColors.default.sw-color-warning.label',
                                'helpText' => null,
                                'helpTextSnippetKey' => 'sw-theme.test.default.statusColors.default.sw-color-warning.helpText',
                                'type' => 'color',
                                'custom' => null,
                                'fullWidth' => null,
                            ],
                            'sw-color-danger' => [
                                'label' => null,
                                'labelSnippetKey' => 'sw-theme.test.default.statusColors.default.sw-color-danger.label',
                                'helpText' => null,
                                'helpTextSnippetKey' => 'sw-theme.test.default.statusColors.default.sw-color-danger.helpText',
                                'type' => 'color',
                                'custom' => null,
                                'fullWidth' => null,
                            ],
                        ],
                    ],
                ],
            ],
            'typography' => [
                'label' => 'typography',
                'labelSnippetKey' => 'sw-theme.test.default.typography.label',
                'sections' => [
                    'default' => [
                        'label' => null,
                        'labelSnippetKey' => 'sw-theme.test.default.typography.default.label',
                        'fields' => [
                            'sw-font-family-base' => [
                                'label' => null,
                                'labelSnippetKey' => 'sw-theme.test.default.typography.default.sw-font-family-base.label',
                                'helpTextSnippetKey' => 'sw-theme.test.default.typography.default.sw-font-family-base.helpText',
                                'helpText' => null,
                                'type' => 'fontFamily',
                                'custom' => null,
                                'fullWidth' => null,
                            ],
                            'sw-text-color' => [
                                'label' => null,
                                'labelSnippetKey' => 'sw-theme.test.default.typography.default.sw-text-color.label',
                                'helpTextSnippetKey' => 'sw-theme.test.default.typography.default.sw-text-color.helpText',
                                'helpText' => null,
                                'type' => 'color',
                                'custom' => null,
                                'fullWidth' => null,
                            ],
                            'sw-font-family-headline' => [
                                'label' => null,
                                'labelSnippetKey' => 'sw-theme.test.default.typography.default.sw-font-family-headline.label',
                                'helpTextSnippetKey' => 'sw-theme.test.default.typography.default.sw-font-family-headline.helpText',
                                'helpText' => null,
                                'type' => 'fontFamily',
                                'custom' => null,
                                'fullWidth' => null,
                            ],
                            'sw-headline-color' => [
                                'label' => null,
                                'labelSnippetKey' => 'sw-theme.test.default.typography.default.sw-headline-color.label',
                                'helpTextSnippetKey' => 'sw-theme.test.default.typography.default.sw-headline-color.helpText',
                                'helpText' => null,
                                'type' => 'color',
                                'custom' => null,
                                'fullWidth' => null,
                            ],
                        ],
                    ],
                ],
            ],
            'eCommerce' => [
                'label' => 'eCommerce',
                'labelSnippetKey' => 'sw-theme.test.default.eCommerce.label',
                'sections' => [
                    'default' => [
                        'label' => null,
                        'labelSnippetKey' => 'sw-theme.test.default.eCommerce.default.label',
                        'fields' => [
                            'sw-color-price' => [
                                'label' => null,
                                'labelSnippetKey' => 'sw-theme.test.default.eCommerce.default.sw-color-price.label',
                                'helpTextSnippetKey' => 'sw-theme.test.default.eCommerce.default.sw-color-price.helpText',
                                'helpText' => null,
                                'type' => 'color',
                                'custom' => null,
                                'fullWidth' => null,
                            ],
                            'sw-color-buy-button' => [
                                'label' => null,
                                'labelSnippetKey' => 'sw-theme.test.default.eCommerce.default.sw-color-buy-button.label',
                                'helpTextSnippetKey' => 'sw-theme.test.default.eCommerce.default.sw-color-buy-button.helpText',
                                'helpText' => null,
                                'type' => 'color',
                                'custom' => null,
                                'fullWidth' => null,
                            ],
                            'sw-color-buy-button-text' => [
                                'label' => null,
                                'labelSnippetKey' => 'sw-theme.test.default.eCommerce.default.sw-color-buy-button-text.label',
                                'helpTextSnippetKey' => 'sw-theme.test.default.eCommerce.default.sw-color-buy-button-text.helpText',
                                'helpText' => null,
                                'type' => 'color',
                                'custom' => null,
                                'fullWidth' => null,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedTabs2(): array
    {
        return [
            'default' => [
                'label' => null,
                'labelSnippetKey' => 'sw-theme.test.default.label',
                'blocks' => array_merge(ThemeFixtures::getExtractedTabsSub1(), [
                    'media' => [
                        'label' => 'media',
                        'labelSnippetKey' => 'sw-theme.test.default.media.label',
                        'sections' => [
                            'default' => [
                                'label' => '',
                                'labelSnippetKey' => 'sw-theme.test.default.media.default.label',
                                'fields' => [
                                    'sw-logo-desktop' => [
                                        'label' => null,
                                        'labelSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-desktop.label',
                                        'helpTextSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-desktop.helpText',
                                        'helpText' => null,
                                        'type' => 'media',
                                        'custom' => null,
                                        'fullWidth' => true,
                                    ],
                                    'sw-logo-tablet' => [
                                        'label' => null,
                                        'labelSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-tablet.label',
                                        'helpTextSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-tablet.helpText',
                                        'helpText' => null,
                                        'type' => 'media',
                                        'custom' => null,
                                        'fullWidth' => true,
                                    ],
                                    'sw-logo-mobile' => [
                                        'label' => null,
                                        'labelSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-mobile.label',
                                        'helpTextSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-mobile.helpText',
                                        'helpText' => null,
                                        'type' => 'media',
                                        'custom' => null,
                                        'fullWidth' => true,
                                    ],
                                    'sw-logo-share' => [
                                        'label' => null,
                                        'labelSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-share.label',
                                        'helpTextSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-share.helpText',
                                        'helpText' => null,
                                        'type' => 'media',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'sw-logo-favicon' => [
                                        'label' => null,
                                        'labelSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-favicon.label',
                                        'helpTextSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-favicon.helpText',
                                        'helpText' => null,
                                        'type' => 'media',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'default' => [
                        'label' => null,
                        'labelSnippetKey' => 'sw-theme.test.default.default.label',
                        'sections' => [
                            'default' => [
                                'label' => null,
                                'labelSnippetKey' => 'sw-theme.test.default.default.default.label',
                                'fields' => [
                                    'test' => [
                                        'label' => null,
                                        'labelSnippetKey' => 'sw-theme.test.default.default.default.test.label',
                                        'helpTextSnippetKey' => 'sw-theme.test.default.default.default.test.helpText',
                                        'helpText' => null,
                                        'type' => null,
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedFields3(): array
    {
        return [
            'sw-color-brand-primary' => [
                'extensions' => [
                ],
                'name' => 'sw-color-brand-primary',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#008490',
                'editable' => true,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-brand-secondary' => [
                'extensions' => [
                ],
                'name' => 'sw-color-brand-secondary',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#526e7f',
                'editable' => true,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-border-color' => [
                'extensions' => [
                ],
                'name' => 'sw-border-color',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#bcc1c7',
                'editable' => true,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-background-color' => [
                'extensions' => [
                ],
                'name' => 'sw-background-color',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#fff',
                'editable' => true,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-success' => [
                'extensions' => [
                ],
                'name' => 'sw-color-success',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#3cc261',
                'editable' => true,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-info' => [
                'extensions' => [
                ],
                'name' => 'sw-color-info',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#26b6cf',
                'editable' => true,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-warning' => [
                'extensions' => [
                ],
                'name' => 'sw-color-warning',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#ffbd5d',
                'editable' => true,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-danger' => [
                'extensions' => [
                ],
                'name' => 'sw-color-danger',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#e52427',
                'editable' => true,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-font-family-base' => [
                'extensions' => [
                ],
                'name' => 'sw-font-family-base',
                'label' => null,
                'helpText' => null,
                'type' => 'fontFamily',
                'value' => '\'Inter\', sans-serif',
                'editable' => true,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-text-color' => [
                'extensions' => [
                ],
                'name' => 'sw-text-color',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#4a545b',
                'editable' => true,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-font-family-headline' => [
                'extensions' => [
                ],
                'name' => 'sw-font-family-headline',
                'label' => null,
                'helpText' => null,
                'type' => 'fontFamily',
                'value' => '\'Inter\', sans-serif',
                'editable' => true,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-headline-color' => [
                'extensions' => [
                ],
                'name' => 'sw-headline-color',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#4a545b',
                'editable' => true,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-price' => [
                'extensions' => [
                ],
                'name' => 'sw-color-price',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#4a545b',
                'editable' => true,
                'block' => 'eCommerce',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-buy-button' => [
                'extensions' => [
                ],
                'name' => 'sw-color-buy-button',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#008490',
                'editable' => true,
                'block' => 'eCommerce',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-color-buy-button-text' => [
                'extensions' => [
                ],
                'name' => 'sw-color-buy-button-text',
                'label' => null,
                'helpText' => null,
                'type' => 'color',
                'value' => '#fff',
                'editable' => true,
                'block' => 'eCommerce',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-logo-desktop' => [
                'extensions' => [
                ],
                'name' => 'sw-logo-desktop',
                'label' => null,
                'helpText' => null,
                'type' => 'media',
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
                'editable' => true,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => true,
            ],
            'sw-logo-tablet' => [
                'extensions' => [
                ],
                'name' => 'sw-logo-tablet',
                'label' => null,
                'helpText' => null,
                'type' => 'media',
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
                'editable' => true,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => true,
            ],
            'sw-logo-mobile' => [
                'extensions' => [
                ],
                'name' => 'sw-logo-mobile',
                'label' => null,
                'helpText' => null,
                'type' => 'media',
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
                'editable' => true,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => true,
            ],
            'sw-logo-share' => [
                'extensions' => [
                ],
                'name' => 'sw-logo-share',
                'label' => null,
                'helpText' => null,
                'type' => 'media',
                'value' => null,
                'editable' => true,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'sw-logo-favicon' => [
                'extensions' => [
                ],
                'name' => 'sw-logo-favicon',
                'label' => null,
                'helpText' => null,
                'type' => 'media',
                'value' => 'app/storefront/dist/assets/logo/favicon.png',
                'editable' => true,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 500,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'first' => [
                'extensions' => [
                ],
                'name' => 'first',
                'label' => null,
                'helpText' => null,
                'value' => null,
                'editable' => null,
                'block' => null,
                'section' => null,
                'tab' => null,
                'order' => null,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'test' => [
                'extensions' => [
                ],
                'name' => 'test',
                'label' => null,
                'helpText' => null,
                'value' => [
                    0 => 'no_test',
                ],
                'editable' => null,
                'block' => null,
                'section' => null,
                'tab' => null,
                'order' => null,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedCurrentFields2(): array
    {
        return [
            'sw-color-brand-primary' => [
                'isInherited' => null,
                'value' => '#008490',
            ],
            'sw-color-brand-secondary' => [
                'isInherited' => null,
                'value' => '#526e7f',
            ],
            'sw-border-color' => [
                'isInherited' => null,
                'value' => '#bcc1c7',
            ],
            'sw-background-color' => [
                'isInherited' => null,
                'value' => '#fff',
            ],
            'sw-color-success' => [
                'isInherited' => null,
                'value' => '#3cc261',
            ],
            'sw-color-info' => [
                'isInherited' => null,
                'value' => '#26b6cf',
            ],
            'sw-color-warning' => [
                'isInherited' => null,
                'value' => '#ffbd5d',
            ],
            'sw-color-danger' => [
                'isInherited' => null,
                'value' => '#e52427',
            ],
            'sw-font-family-base' => [
                'isInherited' => null,
                'value' => '\'Inter\', sans-serif',
            ],
            'sw-text-color' => [
                'isInherited' => null,
                'value' => '#4a545b',
            ],
            'sw-font-family-headline' => [
                'isInherited' => null,
                'value' => '\'Inter\', sans-serif',
            ],
            'sw-headline-color' => [
                'isInherited' => null,
                'value' => '#4a545b',
            ],
            'sw-color-price' => [
                'isInherited' => null,
                'value' => '#4a545b',
            ],
            'sw-color-buy-button' => [
                'isInherited' => null,
                'value' => '#008490',
            ],
            'sw-color-buy-button-text' => [
                'isInherited' => null,
                'value' => '#fff',
            ],
            'sw-logo-desktop' => [
                'isInherited' => null,
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
            ],
            'sw-logo-tablet' => [
                'isInherited' => null,
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
            ],
            'sw-logo-mobile' => [
                'isInherited' => null,
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
            ],
            'sw-logo-share' => [
                'isInherited' => null,
                'value' => null,
            ],
            'sw-logo-favicon' => [
                'isInherited' => null,
                'value' => 'app/storefront/dist/assets/logo/favicon.png',
            ],
            'first' => [
                'isInherited' => null,
                'value' => null,
            ],
            'test' => [
                'isInherited' => null,
                'value' => [
                    0 => 'no_test',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedBaseThemeFields2(): array
    {
        return [
            'sw-color-brand-primary' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-color-brand-secondary' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-border-color' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-background-color' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-color-success' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-color-info' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-color-warning' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-color-danger' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-font-family-base' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-text-color' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-font-family-headline' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-headline-color' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-color-price' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-color-buy-button' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-color-buy-button-text' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-logo-desktop' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-logo-tablet' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-logo-mobile' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-logo-share' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'sw-logo-favicon' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'first' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'test' => [
                'isInherited' => 1,
                'value' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedFields4(): array
    {
        return array_merge(ThemeFixtures::getExtractedFieldsSub1(), [
            'first' => [
                'extensions' => [
                ],
                'name' => 'first',
                'label' => null,
                'helpText' => null,
                'value' => null,
                'editable' => null,
                'block' => null,
                'section' => null,
                'tab' => null,
                'order' => null,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'test' => [
                'extensions' => [
                ],
                'name' => 'test',
                'label' => null,
                'helpText' => null,
                'value' => [
                    0 => 'no_test',
                ],
                'editable' => null,
                'block' => null,
                'section' => null,
                'tab' => null,
                'order' => null,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedFields5(): array
    {
        return [
            ...ThemeFixtures::getExtractedFieldsSub1(),
            'test' => [
                'extensions' => [
                ],
                'name' => 'test',
                'label' => null,
                'helpText' => null,
                'value' => [
                    0 => 'no_test',
                ],
                'editable' => null,
                'block' => null,
                'section' => null,
                'tab' => null,
                'order' => null,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedCurrentFields3(): array
    {
        return [
            ...self::getExtractedCurrentFields1(),
            'test' => [
                'isInherited' => null,
                'value' => [
                    0 => 'no_test',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedBaseThemeFields3(): array
    {
        return [
            ...self::getExtractedBaseThemeFields1(),
            'test' => [
                'isInherited' => 1,
                'value' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedTabs5(): array
    {
        return [
            'default' => [
                'label' => '',
                'labelSnippetKey' => 'sw-theme.test.default.label',
                'blocks' => [
                    'default' => [
                        'label' => '',
                        'labelSnippetKey' => 'sw-theme.test.default.default.label',
                        'sections' => [
                            'default' => [
                                'label' => '',
                                'labelSnippetKey' => 'sw-theme.test.default.default.default.label',
                                'fields' => [
                                    'test' => [
                                        'label' => 'test',
                                        'labelSnippetKey' => 'sw-theme.test.default.default.default.test.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.test.default.default.default.test.helpText',
                                        'type' => null,
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'themeColors' => [
                        'label' => 'themeColors',
                        'labelSnippetKey' => 'sw-theme.test.default.themeColors.label',
                        'sections' => ThemeFixtures::getExtractedSectionsThemeColors(),
                    ],
                    'statusColors' => [
                        'label' => 'statusColors',
                        'labelSnippetKey' => 'sw-theme.test.default.statusColors.label',
                        'sections' => ThemeFixtures::getExtractedSectionsStatusColors(),
                    ],
                    'typography' => [
                        'label' => 'typography',
                        'labelSnippetKey' => 'sw-theme.test.default.typography.label',
                        'sections' => ThemeFixtures::getExtractedSectionsTypography(),
                    ],
                    'eCommerce' => [
                        'label' => 'eCommerce',
                        'labelSnippetKey' => 'sw-theme.test.default.eCommerce.label',
                        'sections' => ThemeFixtures::getExtractedSectionsECommerce(),
                    ],
                    'media' => [
                        'label' => 'media',
                        'labelSnippetKey' => 'sw-theme.test.default.media.label',
                        'sections' => ThemeFixtures::getExtractedSectionsMedia(),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedTabs6(): array
    {
        return [
            'default' => [
                'label' => '',
                'labelSnippetKey' => 'sw-theme.test.default.label',
                'blocks' => [
                    'default' => [
                        'label' => '',
                        'labelSnippetKey' => 'sw-theme.test.default.default.label',
                        'sections' => [
                            'default' => [
                                'label' => '',
                                'labelSnippetKey' => 'sw-theme.test.default.default.default.label',
                                'fields' => [
                                    'test' => [
                                        'label' => null,
                                        'labelSnippetKey' => 'sw-theme.test.default.default.default.test.label',
                                        'helpText' => null,
                                        'type' => null,
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'themeColors' => [
                        'label' => 'themeColors',
                        'labelSnippetKey' => 'sw-theme.test.default.themeColors.label',
                        'sections' => ThemeFixtures::getExtractedSectionsThemeColors(),
                    ],
                    'statusColors' => [
                        'label' => 'statusColors',
                        'labelSnippetKey' => 'sw-theme.test.default.statusColors.label',
                        'sections' => ThemeFixtures::getExtractedSectionsStatusColors(),
                    ],
                    'typography' => [
                        'label' => 'typography',
                        'labelSnippetKey' => 'sw-theme.test.default.typography.label',
                        'sections' => ThemeFixtures::getExtractedSectionsTypography(),
                    ],
                    'eCommerce' => [
                        'label' => 'eCommerce',
                        'labelSnippetKey' => 'sw-theme.test.default.eCommerce.label',
                        'sections' => ThemeFixtures::getExtractedSectionsECommerce(),
                    ],
                    'media' => [
                        'label' => 'media',
                        'labelSnippetKey' => 'sw-theme.test.default.media.label',
                        'sections' => ThemeFixtures::getExtractedSectionsMedia(),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedFields7(): array
    {
        return [...ThemeFixtures::getExtractedFields1(), ...[
            'parent-custom-config' => [
                'extensions' => [
                ],
                'name' => 'parent-custom-config',
                'label' => 'EN',
                'helpText' => 'EN Helptext',
                'type' => 'int',
                'value' => '20',
                'editable' => true,
                'block' => null,
                'section' => null,
                'tab' => null,
                'order' => null,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'extend-parent-custom-config' => [
                'extensions' => [
                ],
                'name' => 'extend-parent-custom-config',
                'label' => 'EN',
                'helpText' => 'EN Helptext',
                'type' => 'int',
                'value' => '20',
                'editable' => true,
                'block' => null,
                'section' => null,
                'tab' => null,
                'order' => null,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedFields8(): array
    {
        return [...ThemeFixtures::getExtractedFields2(), ...[
            'parent-custom-config' => [
                'extensions' => [
                ],
                'name' => 'parent-custom-config',
                'label' => null,
                'helpText' => null,
                'type' => 'int',
                'value' => '20',
                'editable' => true,
                'block' => null,
                'section' => null,
                'tab' => null,
                'order' => null,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'extend-parent-custom-config' => [
                'extensions' => [
                ],
                'name' => 'extend-parent-custom-config',
                'label' => null,
                'helpText' => null,
                'type' => 'int',
                'value' => '20',
                'editable' => true,
                'block' => null,
                'section' => null,
                'tab' => null,
                'order' => null,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedFields9(): array
    {
        $fields = ThemeFixtures::getExtractedFieldsSub1();

        $fields['sw-color-brand-primary']['value'] = '#adbd00';

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedFields10(): array
    {
        $fields = ThemeFixtures::getExtractedFields9();

        foreach ($fields as $key => $field) {
            if ($field['editable'] === 1) {
                $fields[$key]['editable'] = true;
            }

            if ($field['fullWidth'] === 1) {
                $fields[$key]['fullWidth'] = true;
            }
        }

        $fields['test-something-with-options'] = [
            'name' => 'test-something-with-options',
            'extensions' => [],
            'label' => null,
            'helpText' => null,
            'type' => 'text',
            'value' => 'Hello',
            'editable' => true,
            'block' => 'media',
            'section' => null,
            'tab' => null,
            'order' => 600,
            'sectionOrder' => null,
            'blockOrder' => null,
            'tabOrder' => null,
            'custom' => [
                'componentName' => 'sw-single-select',
                'options' => [
                    [
                        'value' => 'Hello',
                    ],
                    [
                        'value' => 'World',
                    ],
                ],
            ],
            'scss' => null,
            'fullWidth' => null,
        ];

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedFields11(): array
    {
        $fields = ThemeFixtures::getExtractedFields9();

        $fields['sw-color-brand-secondary']['value'] = '#46801a';

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedCurrentFields5(): array
    {
        return [...ThemeFixtures::getExtractedCurrentFields1(), ...[
            'parent-custom-config' => [
                'value' => null,
                'isInherited' => true,
            ],
            'extend-parent-custom-config' => [
                'value' => '20',
                'isInherited' => false,
            ],
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedCurrentFields6(): array
    {
        return [
            'sw-color-brand-primary' => [
                'isInherited' => false,
                'value' => '#adbd00',
            ],
            'sw-color-brand-secondary' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-border-color' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-background-color' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-color-success' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-color-info' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-color-warning' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-color-danger' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-font-family-base' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-text-color' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-font-family-headline' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-headline-color' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-color-price' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-color-buy-button' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-color-buy-button-text' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-logo-desktop' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-logo-tablet' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-logo-mobile' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-logo-share' => [
                'isInherited' => true,
                'value' => null,
            ],
            'sw-logo-favicon' => [
                'isInherited' => true,
                'value' => null,
            ],
            'test-something-with-options' => [
                'value' => 'Hello',
                'isInherited' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedCurrentFields8(): array
    {
        $currentFields = ThemeFixtures::getExtractedCurrentFields6();

        $currentFields['sw-color-brand-primary'] = [
            'isInherited' => true,
            'value' => null,
        ];

        $currentFields['test'] = [
            'isInherited' => null,
            'value' => [
                0 => 'no_test',
            ],
        ];

        unset($currentFields['test-something-with-options']);

        return $currentFields;
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedBaseThemeFields5(): array
    {
        return [...self::getExtractedBaseThemeFields1(), ...[
            'parent-custom-config' => [
                'isInherited' => 0,
                'value' => 20,
            ],
            'extend-parent-custom-config' => [
                'isInherited' => 1,
                'value' => null,
            ],
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedBaseThemeFields6(): array
    {
        return [
            'sw-color-brand-primary' => [
                'isInherited' => false,
                'value' => '#008490',
            ],
            'sw-color-brand-secondary' => [
                'isInherited' => false,
                'value' => '#526e7f',
            ],
            'sw-border-color' => [
                'isInherited' => false,
                'value' => '#bcc1c7',
            ],
            'sw-background-color' => [
                'isInherited' => false,
                'value' => '#fff',
            ],
            'sw-color-success' => [
                'isInherited' => false,
                'value' => '#3cc261',
            ],
            'sw-color-info' => [
                'isInherited' => false,
                'value' => '#26b6cf',
            ],
            'sw-color-warning' => [
                'isInherited' => false,
                'value' => '#ffbd5d',
            ],
            'sw-color-danger' => [
                'isInherited' => false,
                'value' => '#e52427',
            ],
            'sw-font-family-base' => [
                'isInherited' => false,
                'value' => '\'Inter\', sans-serif',
            ],
            'sw-text-color' => [
                'isInherited' => false,
                'value' => '#4a545b',
            ],
            'sw-font-family-headline' => [
                'isInherited' => false,
                'value' => '\'Inter\', sans-serif',
            ],
            'sw-headline-color' => [
                'isInherited' => false,
                'value' => '#4a545b',
            ],
            'sw-color-price' => [
                'isInherited' => false,
                'value' => '#4a545b',
            ],
            'sw-color-buy-button' => [
                'isInherited' => false,
                'value' => '#008490',
            ],
            'sw-color-buy-button-text' => [
                'isInherited' => false,
                'value' => '#fff',
            ],
            'sw-logo-desktop' => [
                'isInherited' => false,
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
            ],
            'sw-logo-tablet' => [
                'isInherited' => false,
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
            ],
            'sw-logo-mobile' => [
                'isInherited' => false,
                'value' => 'app/storefront/dist/assets/logo/demostore-logo.png',
            ],
            'sw-logo-share' => [
                'isInherited' => false,
                'value' => null,
            ],
            'sw-logo-favicon' => [
                'isInherited' => false,
                'value' => 'app/storefront/dist/assets/logo/favicon.png',
            ],
            'test-something-with-options' => [
                'isInherited' => true,
                'value' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedBaseThemeFields8(): array
    {
        $baseThemeFields = ThemeFixtures::getExtractedBaseThemeFields6();

        $baseThemeFields['test'] = [
            'isInherited' => true,
            'value' => null,
        ];

        unset($baseThemeFields['test-something-with-options']);

        return $baseThemeFields;
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedTabs10(): array
    {
        return [
            'default' => [
                'label' => '',
                'labelSnippetKey' => 'sw-theme.test.default.label',
                'blocks' => [
                    'themeColors' => [
                        'label' => 'themeColors',
                        'labelSnippetKey' => 'sw-theme.test.default.themeColors.label',
                        'sections' => ThemeFixtures::getExtractedSectionsThemeColors(),
                    ],
                    'statusColors' => [
                        'label' => 'statusColors',
                        'labelSnippetKey' => 'sw-theme.test.default.statusColors.label',
                        'sections' => ThemeFixtures::getExtractedSectionsStatusColors(),
                    ],
                    'typography' => [
                        'label' => 'typography',
                        'labelSnippetKey' => 'sw-theme.test.default.typography.label',
                        'sections' => ThemeFixtures::getExtractedSectionsTypography(),
                    ],
                    'eCommerce' => [
                        'label' => 'eCommerce',
                        'labelSnippetKey' => 'sw-theme.test.default.eCommerce.label',
                        'sections' => ThemeFixtures::getExtractedSectionsECommerce(),
                    ],
                    'media' => [
                        'label' => 'media',
                        'labelSnippetKey' => 'sw-theme.test.default.media.label',
                        'sections' => ThemeFixtures::getExtractedSectionsMediaNoHelpTexts(),
                    ],
                    'default' => [
                        'label' => '',
                        'labelSnippetKey' => 'sw-theme.test.default.default.label',
                        'sections' => [
                            'default' => [
                                'label' => '',
                                'labelSnippetKey' => 'sw-theme.test.default.default.default.label',
                                'fields' => [
                                    'test' => [
                                        'label' => 'test',
                                        'labelSnippetKey' => 'sw-theme.test.default.default.default.test.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.test.default.default.default.test.helpText',
                                        'type' => null,
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'parent-custom-config' => [
                                        'label' => 'EN',
                                        'labelSnippetKey' => 'sw-theme.test.default.default.default.parent-custom-config.label',
                                        'helpText' => 'EN Helptext',
                                        'helpTextSnippetKey' => 'sw-theme.test.default.default.default.parent-custom-config.helpText',
                                        'type' => 'int',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'extend-parent-custom-config' => [
                                        'label' => 'EN',
                                        'labelSnippetKey' => 'sw-theme.test.default.default.default.extend-parent-custom-config.label',
                                        'helpText' => 'EN Helptext',
                                        'helpTextSnippetKey' => 'sw-theme.test.default.default.default.extend-parent-custom-config.helpText',
                                        'type' => 'int',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedTabs11(): array
    {
        $expected = ThemeFixtures::getExtractedTabs2();

        $fields = $expected['default']['blocks']['default']['sections']['default']['fields'];
        $expected['default']['blocks']['default']['sections']['default']['fields'] = array_merge($fields, [
            'parent-custom-config' => [
                'label' => [
                    'de-DE' => 'DE',
                    'en-GB' => 'EN',
                ],
                'labelSnippetKey' => 'sw-theme.test.default.default.default.parent-custom-config.label',
                'helpText' => [
                    'de-DE' => 'De Helptext',
                    'en-GB' => 'EN Helptext',
                ],
                'type' => 'int',
                'custom' => null,
                'fullWidth' => null,
            ],
            'extend-parent-custom-config' => [
                'label' => [
                    'de-DE' => 'DE',
                    'en-GB' => 'EN',
                ],
                'labelSnippetKey' => 'sw-theme.test.default.default.default.extend-parent-custom-config.label',
                'helpText' => [
                    'de-DE' => 'De Helptext',
                    'en-GB' => 'EN Helptext',
                ],
                'type' => 'int',
                'custom' => null,
                'fullWidth' => null,
            ],
        ]);

        return $expected;
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedTabsNameTheme(): array
    {
        return [
            'default' => [
                'label' => '',
                'labelSnippetKey' => 'sw-theme.theme.default.label',
                'blocks' => [
                    'themeColors' => [
                        'label' => 'themeColors',
                        'labelSnippetKey' => 'sw-theme.theme.default.themeColors.label',
                        'sections' => [
                            'default' => [
                                'label' => '',
                                'labelSnippetKey' => 'sw-theme.theme.default.themeColors.default.label',
                                'fields' => [
                                    'sw-color-brand-primary' => [
                                        'label' => 'sw-color-brand-primary',
                                        'labelSnippetKey' => 'sw-theme.theme.default.themeColors.default.sw-color-brand-primary.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.themeColors.default.sw-color-brand-primary.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'sw-color-brand-secondary' => [
                                        'label' => 'sw-color-brand-secondary',
                                        'labelSnippetKey' => 'sw-theme.theme.default.themeColors.default.sw-color-brand-secondary.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.themeColors.default.sw-color-brand-secondary.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'sw-border-color' => [
                                        'label' => 'sw-border-color',
                                        'labelSnippetKey' => 'sw-theme.theme.default.themeColors.default.sw-border-color.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.themeColors.default.sw-border-color.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'sw-background-color' => [
                                        'label' => 'sw-background-color',
                                        'labelSnippetKey' => 'sw-theme.theme.default.themeColors.default.sw-background-color.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.themeColors.default.sw-background-color.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'statusColors' => [
                        'label' => 'statusColors',
                        'labelSnippetKey' => 'sw-theme.theme.default.statusColors.label',
                        'sections' => [
                            'default' => [
                                'label' => '',
                                'labelSnippetKey' => 'sw-theme.theme.default.statusColors.default.label',
                                'fields' => [
                                    'sw-color-success' => [
                                        'label' => 'sw-color-success',
                                        'labelSnippetKey' => 'sw-theme.theme.default.statusColors.default.sw-color-success.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.statusColors.default.sw-color-success.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'sw-color-info' => [
                                        'label' => 'sw-color-info',
                                        'labelSnippetKey' => 'sw-theme.theme.default.statusColors.default.sw-color-info.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.statusColors.default.sw-color-info.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'sw-color-warning' => [
                                        'label' => 'sw-color-warning',
                                        'labelSnippetKey' => 'sw-theme.theme.default.statusColors.default.sw-color-warning.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.statusColors.default.sw-color-warning.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'sw-color-danger' => [
                                        'label' => 'sw-color-danger',
                                        'labelSnippetKey' => 'sw-theme.theme.default.statusColors.default.sw-color-danger.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.statusColors.default.sw-color-danger.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'typography' => [
                        'label' => 'typography',
                        'labelSnippetKey' => 'sw-theme.theme.default.typography.label',
                        'sections' => [
                            'default' => [
                                'label' => '',
                                'labelSnippetKey' => 'sw-theme.theme.default.typography.default.label',
                                'fields' => [
                                    'sw-font-family-base' => [
                                        'label' => 'sw-font-family-base',
                                        'labelSnippetKey' => 'sw-theme.theme.default.typography.default.sw-font-family-base.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.typography.default.sw-font-family-base.helpText',
                                        'type' => 'fontFamily',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'sw-text-color' => [
                                        'label' => 'sw-text-color',
                                        'labelSnippetKey' => 'sw-theme.theme.default.typography.default.sw-text-color.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.typography.default.sw-text-color.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'sw-font-family-headline' => [
                                        'label' => 'sw-font-family-headline',
                                        'labelSnippetKey' => 'sw-theme.theme.default.typography.default.sw-font-family-headline.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.typography.default.sw-font-family-headline.helpText',
                                        'type' => 'fontFamily',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'sw-headline-color' => [
                                        'label' => 'sw-headline-color',
                                        'labelSnippetKey' => 'sw-theme.theme.default.typography.default.sw-headline-color.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.typography.default.sw-headline-color.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'eCommerce' => [
                        'label' => 'eCommerce',
                        'labelSnippetKey' => 'sw-theme.theme.default.eCommerce.label',
                        'sections' => [
                            'default' => [
                                'label' => '',
                                'labelSnippetKey' => 'sw-theme.theme.default.eCommerce.default.label',
                                'fields' => [
                                    'sw-color-price' => [
                                        'label' => 'sw-color-price',
                                        'labelSnippetKey' => 'sw-theme.theme.default.eCommerce.default.sw-color-price.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.eCommerce.default.sw-color-price.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'sw-color-buy-button' => [
                                        'label' => 'sw-color-buy-button',
                                        'labelSnippetKey' => 'sw-theme.theme.default.eCommerce.default.sw-color-buy-button.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.eCommerce.default.sw-color-buy-button.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'sw-color-buy-button-text' => [
                                        'label' => 'sw-color-buy-button-text',
                                        'labelSnippetKey' => 'sw-theme.theme.default.eCommerce.default.sw-color-buy-button-text.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.eCommerce.default.sw-color-buy-button-text.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'media' => [
                        'label' => 'media',
                        'labelSnippetKey' => 'sw-theme.theme.default.media.label',
                        'sections' => [
                            'default' => [
                                'label' => '',
                                'labelSnippetKey' => 'sw-theme.theme.default.media.default.label',
                                'fields' => [
                                    'sw-logo-desktop' => [
                                        'label' => 'sw-logo-desktop',
                                        'labelSnippetKey' => 'sw-theme.theme.default.media.default.sw-logo-desktop.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.media.default.sw-logo-desktop.helpText',
                                        'type' => 'media',
                                        'custom' => null,
                                        'fullWidth' => true,
                                    ],
                                    'sw-logo-tablet' => [
                                        'label' => 'sw-logo-tablet',
                                        'labelSnippetKey' => 'sw-theme.theme.default.media.default.sw-logo-tablet.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.media.default.sw-logo-tablet.helpText',
                                        'type' => 'media',
                                        'custom' => null,
                                        'fullWidth' => true,
                                    ],
                                    'sw-logo-mobile' => [
                                        'label' => 'sw-logo-mobile',
                                        'labelSnippetKey' => 'sw-theme.theme.default.media.default.sw-logo-mobile.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.media.default.sw-logo-mobile.helpText',
                                        'type' => 'media',
                                        'custom' => null,
                                        'fullWidth' => true,
                                    ],
                                    'sw-logo-share' => [
                                        'label' => 'sw-logo-share',
                                        'labelSnippetKey' => 'sw-theme.theme.default.media.default.sw-logo-share.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.media.default.sw-logo-share.helpText',
                                        'type' => 'media',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'sw-logo-favicon' => [
                                        'label' => 'sw-logo-favicon',
                                        'labelSnippetKey' => 'sw-theme.theme.default.media.default.sw-logo-favicon.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.media.default.sw-logo-favicon.helpText',
                                        'type' => 'media',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'test-something-with-options' => [
                                        'type' => 'text',
                                        'label' => 'test-something-with-options',
                                        'labelSnippetKey' => 'sw-theme.theme.default.media.default.test-something-with-options.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.theme.default.media.default.test-something-with-options.helpText',
                                        'fullWidth' => null,
                                        'custom' => [
                                            'componentName' => 'sw-single-select',
                                            'options' => [
                                                [
                                                    'value' => 'Hello',
                                                    'labelSnippetKey' => 'sw-theme.theme.default.media.default.test-something-with-options.0.label',
                                                ],
                                                [
                                                    'value' => 'World',
                                                    'labelSnippetKey' => 'sw-theme.theme.default.media.default.test-something-with-options.1.label',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedTabs13(): array
    {
        $tabs = [
            'default' => [
                'label' => '',
                'labelSnippetKey' => 'sw-theme.test.default.label',
                'blocks' => array_merge(ThemeFixtures::getExtractedTabsSub1(), [
                    'media' => [
                        'label' => 'media',
                        'labelSnippetKey' => 'sw-theme.test.default.default.label',
                        'sections' => [
                            'default' => [
                                'label' => '',
                                'labelSnippetKey' => 'sw-theme.test.default.default.default.label',
                                'fields' => [
                                    'sw-logo-desktop' => [
                                        'label' => [
                                            'en-GB' => 'Desktop',
                                            'de-DE' => 'Desktop',
                                        ],
                                        'labelSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-desktop.label',
                                        'helpText' => [
                                            'en-GB' => 'Displayed on viewport sizes above 991px and as a fallback on smaller viewports, if no other logo is set.',
                                            'de-DE' => 'Wird bei Ansichten über 991px angezeigt und als Alternative bei kleineren Auflösungen, für die kein anderes Logo eingestellt ist.',
                                        ],
                                        'type' => 'media',
                                        'custom' => null,
                                        'fullWidth' => true,
                                    ],
                                    'sw-logo-tablet' => [
                                        'label' => [
                                            'en-GB' => 'Tablet',
                                            'de-DE' => 'Tablet',
                                        ],
                                        'labelSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-tablet.label',
                                        'helpText' => [
                                            'en-GB' => 'Displayed between a viewport of 767px to 991px',
                                            'de-DE' => 'Wird zwischen einem viewport von 767px bis 991px angezeigt',
                                        ],
                                        'type' => 'media',
                                        'custom' => null,
                                        'fullWidth' => true,
                                    ],
                                    'sw-logo-mobile' => [
                                        'label' => [
                                            'en-GB' => 'Mobile',
                                            'de-DE' => 'Mobil',
                                        ],
                                        'labelSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-mobile.label',
                                        'helpText' => [
                                            'en-GB' => 'Displayed up to a viewport of 767px',
                                            'de-DE' => 'Wird bis zu einem Viewport von 767px angezeigt',
                                        ],
                                        'type' => 'media',
                                        'custom' => null,
                                        'fullWidth' => true,
                                    ],
                                    'sw-logo-share' => [
                                        'label' => [
                                            'en-GB' => 'App & share icon',
                                            'de-DE' => 'App- & Share-Icon',
                                        ],
                                        'labelSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-share.label',
                                        'helpText' => null,
                                        'type' => 'media',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'sw-logo-favicon' => [
                                        'label' => [
                                            'en-GB' => 'Favicon',
                                            'de-DE' => 'Favicon',
                                        ],
                                        'labelSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-favicon.label',
                                        'helpText' => null,
                                        'type' => 'media',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]),
            ],
        ];

        return $tabs;
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedSectionsThemeColors(): array
    {
        return [
            'default' => [
                'label' => '',
                'labelSnippetKey' => 'sw-theme.test.default.themeColors.default.label',
                'fields' => [
                    'sw-color-brand-primary' => [
                        'label' => 'sw-color-brand-primary',
                        'labelSnippetKey' => 'sw-theme.test.default.themeColors.default.sw-color-brand-primary.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.themeColors.default.sw-color-brand-primary.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'sw-color-brand-secondary' => [
                        'label' => 'sw-color-brand-secondary',
                        'labelSnippetKey' => 'sw-theme.test.default.themeColors.default.sw-color-brand-secondary.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.themeColors.default.sw-color-brand-secondary.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'sw-border-color' => [
                        'label' => 'sw-border-color',
                        'labelSnippetKey' => 'sw-theme.test.default.themeColors.default.sw-border-color.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.themeColors.default.sw-border-color.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'sw-background-color' => [
                        'label' => 'sw-background-color',
                        'labelSnippetKey' => 'sw-theme.test.default.themeColors.default.sw-background-color.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.themeColors.default.sw-background-color.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedSectionsStatusColors(): array
    {
        return [
            'default' => [
                'label' => '',
                'labelSnippetKey' => 'sw-theme.test.default.statusColors.default.label',
                'fields' => [
                    'sw-color-success' => [
                        'label' => 'sw-color-success',
                        'labelSnippetKey' => 'sw-theme.test.default.statusColors.default.sw-color-success.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.statusColors.default.sw-color-success.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'sw-color-info' => [
                        'label' => 'sw-color-info',
                        'labelSnippetKey' => 'sw-theme.test.default.statusColors.default.sw-color-info.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.statusColors.default.sw-color-info.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'sw-color-warning' => [
                        'label' => 'sw-color-warning',
                        'labelSnippetKey' => 'sw-theme.test.default.statusColors.default.sw-color-warning.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.statusColors.default.sw-color-warning.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'sw-color-danger' => [
                        'label' => 'sw-color-danger',
                        'labelSnippetKey' => 'sw-theme.test.default.statusColors.default.sw-color-danger.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.statusColors.default.sw-color-danger.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedSectionsTypography(): array
    {
        return [
            'default' => [
                'label' => '',
                'labelSnippetKey' => 'sw-theme.test.default.typography.default.label',
                'fields' => [
                    'sw-font-family-base' => [
                        'label' => 'sw-font-family-base',
                        'labelSnippetKey' => 'sw-theme.test.default.typography.default.sw-font-family-base.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.typography.default.sw-font-family-base.helpText',
                        'type' => 'fontFamily',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'sw-text-color' => [
                        'label' => 'sw-text-color',
                        'labelSnippetKey' => 'sw-theme.test.default.typography.default.sw-text-color.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.typography.default.sw-text-color.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'sw-font-family-headline' => [
                        'label' => 'sw-font-family-headline',
                        'labelSnippetKey' => 'sw-theme.test.default.typography.default.sw-font-family-headline.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.typography.default.sw-font-family-headline.helpText',
                        'type' => 'fontFamily',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'sw-headline-color' => [
                        'label' => 'sw-headline-color',
                        'labelSnippetKey' => 'sw-theme.test.default.typography.default.sw-headline-color.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.typography.default.sw-headline-color.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedSectionsECommerce(): array
    {
        return [
            'default' => [
                'label' => '',
                'labelSnippetKey' => 'sw-theme.test.default.eCommerce.default.label',
                'fields' => [
                    'sw-color-price' => [
                        'label' => 'sw-color-price',
                        'labelSnippetKey' => 'sw-theme.test.default.eCommerce.default.sw-color-price.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.eCommerce.default.sw-color-price.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'sw-color-buy-button' => [
                        'label' => 'sw-color-buy-button',
                        'labelSnippetKey' => 'sw-theme.test.default.eCommerce.default.sw-color-buy-button.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.eCommerce.default.sw-color-buy-button.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'sw-color-buy-button-text' => [
                        'label' => 'sw-color-buy-button-text',
                        'labelSnippetKey' => 'sw-theme.test.default.eCommerce.default.sw-color-buy-button-text.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.eCommerce.default.sw-color-buy-button-text.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedSectionsMedia(): array
    {
        return [
            'default' => [
                'label' => '',
                'labelSnippetKey' => 'sw-theme.test.default.media.default.label',
                'fields' => [
                    'sw-logo-desktop' => [
                        'label' => 'sw-logo-desktop',
                        'labelSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-desktop.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-desktop.helpText',
                        'type' => 'media',
                        'custom' => null,
                        'fullWidth' => true,
                    ],
                    'sw-logo-tablet' => [
                        'label' => 'sw-logo-tablet',
                        'labelSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-tablet.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-tablet.helpText',
                        'type' => 'media',
                        'custom' => null,
                        'fullWidth' => true,
                    ],
                    'sw-logo-mobile' => [
                        'label' => 'sw-logo-mobile',
                        'labelSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-mobile.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-mobile.helpText',
                        'type' => 'media',
                        'custom' => null,
                        'fullWidth' => true,
                    ],
                    'sw-logo-share' => [
                        'label' => 'sw-logo-share',
                        'labelSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-share.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-share.helpText',
                        'type' => 'media',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'sw-logo-favicon' => [
                        'label' => 'sw-logo-favicon',
                        'labelSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-favicon.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-favicon.helpText',
                        'type' => 'media',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedSectionsMediaNoHelpTexts(): array
    {
        return [
            'default' => [
                'label' => '',
                'labelSnippetKey' => 'sw-theme.test.default.media.default.label',
                'fields' => [
                    'sw-logo-desktop' => [
                        'label' => 'sw-logo-desktop',
                        'labelSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-desktop.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-desktop.helpText',
                        'type' => 'media',
                        'custom' => null,
                        'fullWidth' => true,
                    ],
                    'sw-logo-tablet' => [
                        'label' => 'sw-logo-tablet',
                        'labelSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-tablet.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-tablet.helpText',
                        'type' => 'media',
                        'custom' => null,
                        'fullWidth' => true,
                    ],
                    'sw-logo-mobile' => [
                        'label' => 'sw-logo-mobile',
                        'labelSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-mobile.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-mobile.helpText',
                        'type' => 'media',
                        'custom' => null,
                        'fullWidth' => true,
                    ],
                    'sw-logo-share' => [
                        'label' => 'sw-logo-share',
                        'labelSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-share.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-share.helpText',
                        'type' => 'media',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'sw-logo-favicon' => [
                        'label' => 'sw-logo-favicon',
                        'labelSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-favicon.label',
                        'helpText' => null,
                        'helpTextSnippetKey' => 'sw-theme.test.default.media.default.sw-logo-favicon.helpText',
                        'type' => 'media',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedTabs3(): array
    {
        return [
            'default' => [
                'label' => '',
                'labelSnippetKey' => 'sw-theme.test.default.label',
                'blocks' => [
                    'themeColors' => [
                        'label' => 'themeColors',
                        'labelSnippetKey' => 'sw-theme.test.default.themeColors.label',
                        'sections' => ThemeFixtures::getExtractedSectionsThemeColors(),
                    ],
                    'statusColors' => [
                        'label' => 'statusColors',
                        'labelSnippetKey' => 'sw-theme.test.default.statusColors.label',
                        'sections' => ThemeFixtures::getExtractedSectionsStatusColors(),
                    ],
                    'typography' => [
                        'label' => 'typography',
                        'labelSnippetKey' => 'sw-theme.test.default.typography.label',
                        'sections' => ThemeFixtures::getExtractedSectionsTypography(),
                    ],
                    'eCommerce' => [
                        'label' => 'eCommerce',
                        'labelSnippetKey' => 'sw-theme.test.default.eCommerce.label',
                        'sections' => ThemeFixtures::getExtractedSectionsECommerce(),
                    ],
                    'media' => [
                        'label' => 'media',
                        'labelSnippetKey' => 'sw-theme.test.default.media.label',
                        'sections' => ThemeFixtures::getExtractedSectionsMediaNoHelpTexts(),
                    ],
                    'default' => [
                        'label' => '',
                        'labelSnippetKey' => 'sw-theme.test.default.default.label',
                        'sections' => [
                            'default' => [
                                'label' => '',
                                'labelSnippetKey' => 'sw-theme.test.default.default.default.label',
                                'fields' => [
                                    'first' => [
                                        'label' => 'first',
                                        'labelSnippetKey' => 'sw-theme.test.default.default.default.first.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.test.default.default.default.first.helpText',
                                        'type' => null,
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'test' => [
                                        'label' => 'test',
                                        'labelSnippetKey' => 'sw-theme.test.default.default.default.test.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.test.default.default.default.test.helpText',
                                        'type' => null,
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedTabs1(): array
    {
        return [
            'default' => [
                'label' => '',
                'labelSnippetKey' => 'sw-theme.test.default.label',
                'blocks' => [
                    'themeColors' => [
                        'label' => 'themeColors',
                        'labelSnippetKey' => 'sw-theme.test.default.themeColors.label',
                        'sections' => ThemeFixtures::getExtractedSectionsThemeColors(),
                    ],
                    'statusColors' => [
                        'label' => 'statusColors',
                        'labelSnippetKey' => 'sw-theme.test.default.statusColors.label',
                        'sections' => ThemeFixtures::getExtractedSectionsStatusColors(),
                    ],
                    'typography' => [
                        'label' => 'typography',
                        'labelSnippetKey' => 'sw-theme.test.default.typography.label',
                        'sections' => ThemeFixtures::getExtractedSectionsTypography(),
                    ],
                    'eCommerce' => [
                        'label' => 'eCommerce',
                        'labelSnippetKey' => 'sw-theme.test.default.eCommerce.label',
                        'sections' => ThemeFixtures::getExtractedSectionsECommerce(),
                    ],
                    'media' => [
                        'label' => 'media',
                        'labelSnippetKey' => 'sw-theme.test.default.media.label',
                        'sections' => ThemeFixtures::getExtractedSectionsMediaNoHelpTexts(),
                    ],
                    'default' => [
                        'label' => '',
                        'labelSnippetKey' => 'sw-theme.test.default.default.label',
                        'sections' => [
                            'default' => [
                                'label' => '',
                                'labelSnippetKey' => 'sw-theme.test.default.default.default.label',
                                'fields' => [
                                    'test' => [
                                        'label' => 'test',
                                        'labelSnippetKey' => 'sw-theme.test.default.default.default.test.label',
                                        'helpText' => null,
                                        'helpTextSnippetKey' => 'sw-theme.test.default.default.default.test.helpText',
                                        'type' => null,
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getExtractedFields2(): array
    {
        return [...ThemeFixtures::getExtractedFieldsSub1(), ...[
            'test' => [
                'extensions' => [
                ],
                'name' => 'test',
                'label' => null,
                'helpText' => null,
                'value' => [
                    0 => 'no_test',
                ],
                'editable' => null,
                'block' => null,
                'section' => null,
                'tab' => null,
                'order' => null,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
        ]];
    }
}
