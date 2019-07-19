<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme\fixtures;

class ThemeFixtures
{
    public static function getThemeFields(): array
    {
        return [
            'themeColors' => [
                'label' => 'themeColors',
                'sections' => [
                    '' => [
                        'label' => '',
                        'sw-color-brand-primary' => [
                            'label' => [
                                'en-GB' => 'Primary color',
                                'de-DE' => 'Hauptfarbe',
                            ],
                            'type' => 'color',
                        ],
                        'sw-color-brand-secondary' => [
                            'label' => [
                                'en-GB' => 'Secondary color',
                                'de-DE' => 'Sekundärfarbe',
                            ],
                            'type' => 'color',
                        ],
                        'sw-border-color' => [
                            'label' => [
                                'en-GB' => 'Border',
                                'de-DE' => 'Rahmen',
                            ],
                            'type' => 'color',
                        ],
                    ],
                ],
            ],
            'typography' => [
                'label' => 'typography',
                'sections' => [
                    '' => [
                        'sw-font-family-base' => [
                            'label' => [
                                'en-GB' => 'Fonttype text',
                                'de-DE' => 'Schriftart Text',
                            ],
                            'type' => 'fontFamily',
                        ],
                        'sw-font-family-headline' => [
                            'label' => [
                                'en-GB' => 'Headline',
                                'de-DE' => 'Überschrift',
                            ],
                            'type' => 'fontFamily',
                        ],
                        'sw-text-color' => [
                            'label' => [
                                'en-GB' => 'Text color',
                                'de-DE' => 'Textfarbe',
                            ],
                            'type' => 'color',
                        ],
                        'label' => '',
                    ],
                ],
            ],
            'media' => [
                'label' => 'media',
                'sections' => [
                    '' => [
                        'label' => '',
                        'sw-logo-default' => [
                            'label' => [
                                'en-GB' => 'Default',
                                'de-DE' => 'Standard',
                            ],
                            'type' => 'media',
                        ],
                        'sw-logo-default-sm' => [
                            'label' => [
                                'en-GB' => 'Small',
                                'de-DE' => 'Klein',
                            ],
                            'type' => 'media',
                        ],
                        'sw-logo-default-md' => [
                            'label' => [
                                'en-GB' => 'Medium',
                                'de-DE' => 'Mittel',
                            ],
                            'type' => 'media',
                        ],
                        'sw-logo-default-lg' => [
                            'label' => [
                                'en-GB' => 'Large',
                                'de-DE' => 'Groß',
                            ],
                            'type' => 'media',
                        ],
                        'sw-logo-default-xl' => [
                            'label' => [
                                'en-GB' => 'Extra large',
                                'de-DE' => 'Sehr groß',
                            ],
                            'type' => 'media',
                        ],
                        'sw-logo-share' => [
                            'label' => [
                                'en-GB' => 'Share',
                                'de-DE' => 'Teilen',
                            ],
                            'type' => 'media',
                        ],
                        'sw-logo-favicon' => [
                            'label' => [
                                'en-GB' => 'Favicon',
                                'de-DE' => 'Favicon',
                            ],
                            'type' => 'media',
                        ],
                        'sw-logo-app-icon' => [
                            'label' => [
                                'en-GB' => 'App-Icon',
                                'de-DE' => 'App-Icon',
                            ],
                            'type' => 'media',
                        ],
                    ],
                ],
            ],
            'eCommerce' => [
                'label' => 'eCommerce',
                'sections' => [
                    '' => [
                        'label' => '',
                        'sw-color-price' => [
                            'label' => [
                                'en-GB' => 'Price color',
                                'de-DE' => 'Preisfarbe',
                            ],
                            'type' => 'color',
                        ],
                        'sw-color-buy-button' => [
                            'label' => [
                                'en-GB' => 'Buy button',
                                'de-DE' => 'Warenkorb Button',
                            ],
                            'type' => 'color',
                        ],
                    ],
                ],
            ],
            'statusColors' => [
                'label' => 'statusColors',
                'sections' => [
                    '' => [
                        'label' => '',
                        'sw-color-success' => [
                            'label' => [
                                'en-GB' => 'Success',
                                'de-DE' => 'Erfolg',
                            ],
                            'type' => 'color',
                        ],
                        'sw-color-info' => [
                            'label' => [
                                'en-GB' => 'Information',
                                'de-DE' => 'Information',
                            ],
                            'type' => 'color',
                        ],
                        'sw-color-warning' => [
                            'label' => [
                                'en-GB' => 'Notice',
                                'de-DE' => 'Hinweis',
                            ],
                            'type' => 'color',
                        ],
                        'sw-color-danger' => [
                            'label' => [
                                'en-GB' => 'Error',
                                'de-DE' => 'Fehler',
                            ],
                            'type' => 'color',
                        ],
                    ],
                ],
            ],
            'unordered' => [
                'label' => 'unordered',
                'sections' => [
                ],
            ],
        ];
    }

    public static function getThemeInheritedConfig(): array
    {
        return [
            'blocks' => [
                'themeColors' => [
                    'label' => [
                        'en-GB' => 'Theme colors',
                        'de-DE' => 'Theme Farben',
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
                        'de-DE' => 'Status Ausgaben',
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
                        'en-GB' => 'Primary color',
                        'de-DE' => 'Hauptfarbe',
                    ],
                    'type' => 'color',
                    'value' => '#ff00ff',
                    'editable' => true,
                    'block' => 'themeColors',
                    'section' => null,
                    'order' => 100,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-color-brand-secondary' => [
                    'name' => 'sw-color-brand-secondary',
                    'label' => [
                        'en-GB' => 'Secondary color',
                        'de-DE' => 'Sekundärfarbe',
                    ],
                    'type' => 'color',
                    'value' => '#576574',
                    'editable' => true,
                    'block' => 'themeColors',
                    'section' => null,
                    'order' => 200,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-color-success' => [
                    'name' => 'sw-color-success',
                    'label' => [
                        'en-GB' => 'Success',
                        'de-DE' => 'Erfolg',
                    ],
                    'type' => 'color',
                    'value' => '#6ed59f',
                    'editable' => true,
                    'block' => 'statusColors',
                    'section' => null,
                    'order' => 100,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-color-info' => [
                    'name' => 'sw-color-info',
                    'label' => [
                        'en-GB' => 'Information',
                        'de-DE' => 'Information',
                    ],
                    'type' => 'color',
                    'value' => '#76bce7',
                    'editable' => true,
                    'block' => 'statusColors',
                    'section' => null,
                    'order' => 200,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-color-warning' => [
                    'name' => 'sw-color-warning',
                    'label' => [
                        'en-GB' => 'Notice',
                        'de-DE' => 'Hinweis',
                    ],
                    'type' => 'color',
                    'value' => '#fcc679',
                    'editable' => true,
                    'block' => 'statusColors',
                    'section' => null,
                    'order' => 300,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-color-danger' => [
                    'name' => 'sw-color-danger',
                    'label' => [
                        'en-GB' => 'Error',
                        'de-DE' => 'Fehler',
                    ],
                    'type' => 'color',
                    'value' => '#f27f7f',
                    'editable' => true,
                    'block' => 'statusColors',
                    'section' => null,
                    'order' => 400,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-text-color' => [
                    'name' => 'sw-text-color',
                    'label' => [
                        'en-GB' => 'Text color',
                        'de-DE' => 'Textfarbe',
                    ],
                    'type' => 'color',
                    'value' => '#545454',
                    'editable' => true,
                    'block' => 'typography',
                    'section' => null,
                    'order' => 200,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-border-color' => [
                    'name' => 'sw-border-color',
                    'label' => [
                        'en-GB' => 'Border',
                        'de-DE' => 'Rahmen',
                    ],
                    'type' => 'color',
                    'value' => '#d4e2e2',
                    'editable' => true,
                    'block' => 'themeColors',
                    'section' => null,
                    'order' => 300,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-color-price' => [
                    'name' => 'sw-color-price',
                    'label' => [
                        'en-GB' => 'Price color',
                        'de-DE' => 'Preisfarbe',
                    ],
                    'type' => 'color',
                    'value' => '#3f4c58',
                    'editable' => true,
                    'block' => 'eCommerce',
                    'section' => null,
                    'order' => 100,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-color-buy-button' => [
                    'name' => 'sw-color-buy-button',
                    'label' => [
                        'en-GB' => 'Buy button',
                        'de-DE' => 'Warenkorb Button',
                    ],
                    'type' => 'color',
                    'value' => '#399',
                    'editable' => true,
                    'block' => 'eCommerce',
                    'section' => null,
                    'order' => 200,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
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
                    'extensions' => [
                    ],
                ],
                'sw-font-family-headline' => [
                    'name' => 'sw-font-family-headline',
                    'label' => [
                        'en-GB' => 'Headline',
                        'de-DE' => 'Überschrift',
                    ],
                    'type' => 'fontFamily',
                    'value' => '\'Inter\', sans-serif',
                    'editable' => true,
                    'block' => 'typography',
                    'section' => null,
                    'order' => 300,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-logo-default' => [
                    'name' => 'sw-logo-default',
                    'label' => [
                        'en-GB' => 'Default',
                        'de-DE' => 'Standard',
                    ],
                    'type' => 'media',
                    'value' => '*',
                    'editable' => true,
                    'block' => 'media',
                    'section' => null,
                    'order' => 300,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-logo-default-sm' => [
                    'name' => 'sw-logo-default-sm',
                    'label' => [
                        'en-GB' => 'Small',
                        'de-DE' => 'Klein',
                    ],
                    'type' => 'media',
                    'value' => '*',
                    'editable' => true,
                    'block' => 'media',
                    'section' => null,
                    'order' => 100,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-logo-default-md' => [
                    'name' => 'sw-logo-default-md',
                    'label' => [
                        'en-GB' => 'Medium',
                        'de-DE' => 'Mittel',
                    ],
                    'type' => 'media',
                    'value' => '*',
                    'editable' => true,
                    'block' => 'media',
                    'section' => null,
                    'order' => 200,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-logo-default-lg' => [
                    'name' => 'sw-logo-default-lg',
                    'label' => [
                        'en-GB' => 'Large',
                        'de-DE' => 'Groß',
                    ],
                    'type' => 'media',
                    'value' => '*',
                    'editable' => true,
                    'block' => 'media',
                    'section' => null,
                    'order' => 300,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-logo-default-xl' => [
                    'name' => 'sw-logo-default-xl',
                    'label' => [
                        'en-GB' => 'Extra large',
                        'de-DE' => 'Sehr groß',
                    ],
                    'type' => 'media',
                    'value' => '*',
                    'editable' => true,
                    'block' => 'media',
                    'section' => null,
                    'order' => 400,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-logo-share' => [
                    'name' => 'sw-logo-share',
                    'label' => [
                        'en-GB' => 'Share',
                        'de-DE' => 'Teilen',
                    ],
                    'type' => 'media',
                    'value' => '*',
                    'editable' => true,
                    'block' => 'media',
                    'section' => null,
                    'order' => 500,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-logo-favicon' => [
                    'name' => 'sw-logo-favicon',
                    'label' => [
                        'en-GB' => 'Favicon',
                        'de-DE' => 'Favicon',
                    ],
                    'type' => 'media',
                    'value' => '*',
                    'editable' => true,
                    'block' => 'media',
                    'section' => null,
                    'order' => 600,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-logo-app-icon' => [
                    'name' => 'sw-logo-app-icon',
                    'label' => [
                        'en-GB' => 'App-Icon',
                        'de-DE' => 'App-Icon',
                    ],
                    'type' => 'media',
                    'value' => '*',
                    'editable' => true,
                    'block' => 'media',
                    'section' => null,
                    'order' => 700,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
            ],
        ];
    }

    public static function getThemeConfig(): array
    {
        return [
            'blocks' => [
                'themeColors' => [
                    'label' => [
                        'en-GB' => 'Theme colors',
                        'de-DE' => 'Theme Farben',
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
                        'de-DE' => 'Status Ausgaben',
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
                        'en-GB' => 'Primary color',
                        'de-DE' => 'Hauptfarbe',
                    ],
                    'type' => 'color',
                    'value' => '#399',
                    'editable' => true,
                    'block' => 'themeColors',
                    'section' => null,
                    'order' => 100,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-color-brand-secondary' => [
                    'name' => 'sw-color-brand-secondary',
                    'label' => [
                        'en-GB' => 'Secondary color',
                        'de-DE' => 'Sekundärfarbe',
                    ],
                    'type' => 'color',
                    'value' => '#576574',
                    'editable' => true,
                    'block' => 'themeColors',
                    'section' => null,
                    'order' => 200,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-color-success' => [
                    'name' => 'sw-color-success',
                    'label' => [
                        'en-GB' => 'Success',
                        'de-DE' => 'Erfolg',
                    ],
                    'type' => 'color',
                    'value' => '#6ed59f',
                    'editable' => true,
                    'block' => 'statusColors',
                    'section' => null,
                    'order' => 100,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-color-info' => [
                    'name' => 'sw-color-info',
                    'label' => [
                        'en-GB' => 'Information',
                        'de-DE' => 'Information',
                    ],
                    'type' => 'color',
                    'value' => '#76bce7',
                    'editable' => true,
                    'block' => 'statusColors',
                    'section' => null,
                    'order' => 200,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-color-warning' => [
                    'name' => 'sw-color-warning',
                    'label' => [
                        'en-GB' => 'Notice',
                        'de-DE' => 'Hinweis',
                    ],
                    'type' => 'color',
                    'value' => '#fcc679',
                    'editable' => true,
                    'block' => 'statusColors',
                    'section' => null,
                    'order' => 300,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-color-danger' => [
                    'name' => 'sw-color-danger',
                    'label' => [
                        'en-GB' => 'Error',
                        'de-DE' => 'Fehler',
                    ],
                    'type' => 'color',
                    'value' => '#f27f7f',
                    'editable' => true,
                    'block' => 'statusColors',
                    'section' => null,
                    'order' => 400,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-text-color' => [
                    'name' => 'sw-text-color',
                    'label' => [
                        'en-GB' => 'Text color',
                        'de-DE' => 'Textfarbe',
                    ],
                    'type' => 'color',
                    'value' => '#545454',
                    'editable' => true,
                    'block' => 'typography',
                    'section' => null,
                    'order' => 200,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-border-color' => [
                    'name' => 'sw-border-color',
                    'label' => [
                        'en-GB' => 'Border',
                        'de-DE' => 'Rahmen',
                    ],
                    'type' => 'color',
                    'value' => '#d4e2e2',
                    'editable' => true,
                    'block' => 'themeColors',
                    'section' => null,
                    'order' => 300,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-color-price' => [
                    'name' => 'sw-color-price',
                    'label' => [
                        'en-GB' => 'Price color',
                        'de-DE' => 'Preisfarbe',
                    ],
                    'type' => 'color',
                    'value' => '#3f4c58',
                    'editable' => true,
                    'block' => 'eCommerce',
                    'section' => null,
                    'order' => 100,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-color-buy-button' => [
                    'name' => 'sw-color-buy-button',
                    'label' => [
                        'en-GB' => 'Buy button',
                        'de-DE' => 'Warenkorb Button',
                    ],
                    'type' => 'color',
                    'value' => '#399',
                    'editable' => true,
                    'block' => 'eCommerce',
                    'section' => null,
                    'order' => 200,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
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
                    'extensions' => [
                    ],
                ],
                'sw-font-family-headline' => [
                    'name' => 'sw-font-family-headline',
                    'label' => [
                        'en-GB' => 'Headline',
                        'de-DE' => 'Überschrift',
                    ],
                    'type' => 'fontFamily',
                    'value' => '\'Inter\', sans-serif',
                    'editable' => true,
                    'block' => 'typography',
                    'section' => null,
                    'order' => 300,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-logo-default' => [
                    'name' => 'sw-logo-default',
                    'label' => [
                        'en-GB' => 'Default',
                        'de-DE' => 'Standard',
                    ],
                    'type' => 'media',
                    'value' => '205360eedb64487c924e06cccb1654de',
                    'editable' => true,
                    'block' => 'media',
                    'section' => null,
                    'order' => 300,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-logo-default-sm' => [
                    'name' => 'sw-logo-default-sm',
                    'label' => [
                        'en-GB' => 'Small',
                        'de-DE' => 'Klein',
                    ],
                    'type' => 'media',
                    'value' => '',
                    'editable' => true,
                    'block' => 'media',
                    'section' => null,
                    'order' => 100,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-logo-default-md' => [
                    'name' => 'sw-logo-default-md',
                    'label' => [
                        'en-GB' => 'Medium',
                        'de-DE' => 'Mittel',
                    ],
                    'type' => 'media',
                    'value' => '',
                    'editable' => true,
                    'block' => 'media',
                    'section' => null,
                    'order' => 200,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-logo-default-lg' => [
                    'name' => 'sw-logo-default-lg',
                    'label' => [
                        'en-GB' => 'Large',
                        'de-DE' => 'Groß',
                    ],
                    'type' => 'media',
                    'value' => '',
                    'editable' => true,
                    'block' => 'media',
                    'section' => null,
                    'order' => 300,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-logo-default-xl' => [
                    'name' => 'sw-logo-default-xl',
                    'label' => [
                        'en-GB' => 'Extra large',
                        'de-DE' => 'Sehr groß',
                    ],
                    'type' => 'media',
                    'value' => '',
                    'editable' => true,
                    'block' => 'media',
                    'section' => null,
                    'order' => 400,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-logo-share' => [
                    'name' => 'sw-logo-share',
                    'label' => [
                        'en-GB' => 'Share',
                        'de-DE' => 'Teilen',
                    ],
                    'type' => 'media',
                    'value' => '',
                    'editable' => true,
                    'block' => 'media',
                    'section' => null,
                    'order' => 500,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-logo-favicon' => [
                    'name' => 'sw-logo-favicon',
                    'label' => [
                        'en-GB' => 'Favicon',
                        'de-DE' => 'Favicon',
                    ],
                    'type' => 'media',
                    'value' => '1854686fef6d4b1eaaa37866784a54ef',
                    'editable' => true,
                    'block' => 'media',
                    'section' => null,
                    'order' => 600,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-logo-app-icon' => [
                    'name' => 'sw-logo-app-icon',
                    'label' => [
                        'en-GB' => 'App-Icon',
                        'de-DE' => 'App-Icon',
                    ],
                    'type' => 'media',
                    'value' => '',
                    'editable' => true,
                    'block' => 'media',
                    'section' => null,
                    'order' => 700,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
            ],
        ];
    }
}
