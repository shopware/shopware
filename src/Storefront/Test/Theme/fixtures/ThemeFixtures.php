<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme\fixtures;

class ThemeFixtures
{
    public static function getThemeFields(): array
    {
        return [
            'colors' => [
                'label' => 'colors',
                'sections' => [
                    'generalColors' => [
                        'label' => 'generalColors',
                        'sw-color-brand-primary' => [
                            'label' => [
                                'en-GB' => 'Primary',
                                'de-DE' => 'Primär',
                            ],
                            'type' => 'color',
                        ],
                        'sw-color-brand-secondary' => [
                            'label' => [
                                'en-GB' => 'Secondary',
                                'de-DE' => 'Sekundär',
                            ],
                            'type' => 'color',
                        ],
                        'sw-color-success' => [
                            'label' => [
                                'en-GB' => 'Success',
                                'de-DE' => 'Erfolg',
                            ],
                            'type' => 'color',
                        ],
                        'sw-color-info' => [
                            'label' => [
                                'en-GB' => 'Info',
                                'de-DE' => 'Info',
                            ],
                            'type' => 'color',
                        ],
                        'sw-color-warning' => [
                            'label' => [
                                'en-GB' => 'Warning',
                                'de-DE' => 'Warnung',
                            ],
                            'type' => 'color',
                        ],
                        'sw-color-danger' => [
                            'label' => [
                                'en-GB' => 'Danger',
                                'de-DE' => 'Achtung',
                            ],
                            'type' => 'color',
                        ],
                        'sw-text-color' => [
                            'label' => [
                                'en-GB' => 'Text',
                                'de-DE' => 'Text',
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
                    'additionalColors' => [
                        'label' => 'additionalColors',
                        'sw-color-price' => [
                            'label' => [
                                'en-GB' => 'Price',
                                'de-DE' => 'Preis',
                            ],
                            'type' => 'color',
                        ],
                        'sw-color-buy-button' => [
                            'label' => [
                                'en-GB' => 'Buy button',
                                'de-DE' => 'Kaufen Schaltfläche',
                            ],
                            'type' => 'color',
                        ],
                    ],
                ],
            ],
            'fonts' => [
                'label' => 'fonts',
                'sections' => [
                    'generalFonts' => [
                        'label' => 'generalFonts',
                        'sw-font-family-base' => [
                            'label' => [
                                'en-GB' => 'Default',
                                'de-DE' => 'Allgemein',
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
                    ],
                ],
            ],
            'media' => [
                'label' => 'media',
                'sections' => [
                    'logos' => [
                        'label' => 'logos',
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
                        'sw-logo-default-xl' => [
                            'label' => [
                                'en-GB' => 'Extra large',
                                'de-DE' => 'Sehr groß',
                            ],
                            'type' => 'media',
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
                'colors' => [
                    'label' => [
                        'en-GB' => 'Colors',
                        'de-DE' => 'Farben',
                    ],
                ],
                'fonts' => [
                    'label' => [
                        'en-GB' => 'Fonts',
                        'de-DE' => 'Schriftarten',
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
            ],
            'sections' => [
                'generalColors' => [
                    'label' => [
                        'en-GB' => 'General colors',
                        'de-DE' => 'Allgemeine Farben',
                    ],
                ],
                'additionalColors' => [
                    'label' => [
                        'en-GB' => 'Additional colors',
                        'de-DE' => 'Weitere Farben',
                    ],
                ],
                'generalFonts' => [
                    'label' => [
                        'en-GB' => 'General fonts',
                        'de-DE' => 'Allgemeine Schriftarten',
                    ],
                ],
                'logos' => [
                    'label' => [
                        'en-GB' => 'Logos',
                        'de-DE' => 'Logos',
                    ],
                ],
            ],
            'fields' => [
                'sw-color-brand-primary' => [
                    'name' => 'sw-color-brand-primary',
                    'label' => [
                        'en-GB' => 'Primary',
                        'de-DE' => 'Primär',
                    ],
                    'type' => 'color',
                    'value' => '#ff00ff',
                    'editable' => true,
                    'block' => 'colors',
                    'section' => 'generalColors',
                    'order' => 100,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-color-brand-secondary' => [
                    'name' => 'sw-color-brand-secondary',
                    'label' => [
                        'en-GB' => 'Secondary',
                        'de-DE' => 'Sekundär',
                    ],
                    'type' => 'color',
                    'value' => '#576574',
                    'editable' => true,
                    'block' => 'colors',
                    'section' => 'generalColors',
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
                    'block' => 'colors',
                    'section' => 'generalColors',
                    'order' => 300,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-color-info' => [
                    'name' => 'sw-color-info',
                    'label' => [
                        'en-GB' => 'Info',
                        'de-DE' => 'Info',
                    ],
                    'type' => 'color',
                    'value' => '#76bce7',
                    'editable' => true,
                    'block' => 'colors',
                    'section' => 'generalColors',
                    'order' => 400,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-color-warning' => [
                    'name' => 'sw-color-warning',
                    'label' => [
                        'en-GB' => 'Warning',
                        'de-DE' => 'Warnung',
                    ],
                    'type' => 'color',
                    'value' => '#fcc679',
                    'editable' => true,
                    'block' => 'colors',
                    'section' => 'generalColors',
                    'order' => 500,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-color-danger' => [
                    'name' => 'sw-color-danger',
                    'label' => [
                        'en-GB' => 'Danger',
                        'de-DE' => 'Achtung',
                    ],
                    'type' => 'color',
                    'value' => '#f27f7f',
                    'editable' => true,
                    'block' => 'colors',
                    'section' => 'generalColors',
                    'order' => 600,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-text-color' => [
                    'name' => 'sw-text-color',
                    'label' => [
                        'en-GB' => 'Text',
                        'de-DE' => 'Text',
                    ],
                    'type' => 'color',
                    'value' => '#545454',
                    'editable' => true,
                    'block' => 'colors',
                    'section' => 'generalColors',
                    'order' => 700,
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
                    'block' => 'colors',
                    'section' => 'generalColors',
                    'order' => 800,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-color-price' => [
                    'name' => 'sw-color-price',
                    'label' => [
                        'en-GB' => 'Price',
                        'de-DE' => 'Preis',
                    ],
                    'type' => 'color',
                    'value' => '#3f4c58',
                    'editable' => true,
                    'block' => 'colors',
                    'section' => 'additionalColors',
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
                        'de-DE' => 'Kaufen Schaltfläche',
                    ],
                    'type' => 'color',
                    'value' => '#399',
                    'editable' => true,
                    'block' => 'colors',
                    'section' => 'additionalColors',
                    'order' => 200,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-font-family-base' => [
                    'name' => 'sw-font-family-base',
                    'label' => [
                        'en-GB' => 'Default',
                        'de-DE' => 'Allgemein',
                    ],
                    'type' => 'fontFamily',
                    'value' => '\'Inter\', sans-serif',
                    'editable' => true,
                    'block' => 'fonts',
                    'section' => 'generalFonts',
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
                    'block' => 'fonts',
                    'section' => 'generalFonts',
                    'order' => 200,
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
                    'section' => 'logos',
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
                    'section' => 'logos',
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
                    'section' => 'logos',
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
                    'section' => 'logos',
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
                    'section' => 'logos',
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
                    'section' => 'logos',
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
                    'section' => 'logos',
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
                    'section' => 'logos',
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
                'colors' => [
                    'label' => [
                        'en-GB' => 'Colors',
                        'de-DE' => 'Farben',
                    ],
                ],
                'fonts' => [
                    'label' => [
                        'en-GB' => 'Fonts',
                        'de-DE' => 'Schriftarten',
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
            ],
            'sections' => [
                'generalColors' => [
                    'label' => [
                        'en-GB' => 'General colors',
                        'de-DE' => 'Allgemeine Farben',
                    ],
                ],
                'additionalColors' => [
                    'label' => [
                        'en-GB' => 'Additional colors',
                        'de-DE' => 'Weitere Farben',
                    ],
                ],
                'generalFonts' => [
                    'label' => [
                        'en-GB' => 'General fonts',
                        'de-DE' => 'Allgemeine Schriftarten',
                    ],
                ],
                'logos' => [
                    'label' => [
                        'en-GB' => 'Logos',
                        'de-DE' => 'Logos',
                    ],
                ],
            ],
            'fields' => [
                'sw-color-brand-primary' => [
                    'name' => 'sw-color-brand-primary',
                    'label' => [
                        'en-GB' => 'Primary',
                        'de-DE' => 'Primär',
                    ],
                    'type' => 'color',
                    'value' => '#399',
                    'editable' => true,
                    'block' => 'colors',
                    'section' => 'generalColors',
                    'order' => 100,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-color-brand-secondary' => [
                    'name' => 'sw-color-brand-secondary',
                    'label' => [
                        'en-GB' => 'Secondary',
                        'de-DE' => 'Sekundär',
                    ],
                    'type' => 'color',
                    'value' => '#576574',
                    'editable' => true,
                    'block' => 'colors',
                    'section' => 'generalColors',
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
                    'block' => 'colors',
                    'section' => 'generalColors',
                    'order' => 300,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-color-info' => [
                    'name' => 'sw-color-info',
                    'label' => [
                        'en-GB' => 'Info',
                        'de-DE' => 'Info',
                    ],
                    'type' => 'color',
                    'value' => '#76bce7',
                    'editable' => true,
                    'block' => 'colors',
                    'section' => 'generalColors',
                    'order' => 400,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-color-warning' => [
                    'name' => 'sw-color-warning',
                    'label' => [
                        'en-GB' => 'Warning',
                        'de-DE' => 'Warnung',
                    ],
                    'type' => 'color',
                    'value' => '#fcc679',
                    'editable' => true,
                    'block' => 'colors',
                    'section' => 'generalColors',
                    'order' => 500,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-color-danger' => [
                    'name' => 'sw-color-danger',
                    'label' => [
                        'en-GB' => 'Danger',
                        'de-DE' => 'Achtung',
                    ],
                    'type' => 'color',
                    'value' => '#f27f7f',
                    'editable' => true,
                    'block' => 'colors',
                    'section' => 'generalColors',
                    'order' => 600,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-text-color' => [
                    'name' => 'sw-text-color',
                    'label' => [
                        'en-GB' => 'Text',
                        'de-DE' => 'Text',
                    ],
                    'type' => 'color',
                    'value' => '#545454',
                    'editable' => true,
                    'block' => 'colors',
                    'section' => 'generalColors',
                    'order' => 700,
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
                    'block' => 'colors',
                    'section' => 'generalColors',
                    'order' => 800,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-color-price' => [
                    'name' => 'sw-color-price',
                    'label' => [
                        'en-GB' => 'Price',
                        'de-DE' => 'Preis',
                    ],
                    'type' => 'color',
                    'value' => '#3f4c58',
                    'editable' => true,
                    'block' => 'colors',
                    'section' => 'additionalColors',
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
                        'de-DE' => 'Kaufen Schaltfläche',
                    ],
                    'type' => 'color',
                    'value' => '#399',
                    'editable' => true,
                    'block' => 'colors',
                    'section' => 'additionalColors',
                    'order' => 200,
                    'sectionOrder' => null,
                    'blockOrder' => null,
                    'extensions' => [
                    ],
                ],
                'sw-font-family-base' => [
                    'name' => 'sw-font-family-base',
                    'label' => [
                        'en-GB' => 'Default',
                        'de-DE' => 'Allgemein',
                    ],
                    'type' => 'fontFamily',
                    'value' => '\'Inter\', sans-serif',
                    'editable' => true,
                    'block' => 'fonts',
                    'section' => 'generalFonts',
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
                    'block' => 'fonts',
                    'section' => 'generalFonts',
                    'order' => 200,
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
                    'section' => 'logos',
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
                    'section' => 'logos',
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
                    'section' => 'logos',
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
                    'section' => 'logos',
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
                    'section' => 'logos',
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
                    'section' => 'logos',
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
                    'section' => 'logos',
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
                    'section' => 'logos',
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
