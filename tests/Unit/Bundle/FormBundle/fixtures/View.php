<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Tests\Unit\Bundle\FormBundle\fixtures;

class View
{
    public function getAssign()
    {
        return [
            'forceMail' => 0,
            'id' => '5',
            'sSupport' => [
                    'id' => '5',
                    'name' => 'Kontaktformular',
                    'text' => '
Schreiben Sie uns eine eMail.


Wir freuen uns auf Ihre Kontaktaufnahme.

',
                    'text2' => '
Ihr Formular wurde versendet!

',
                    'email' => 'info@example.com',
                    'email_template' => 'Kontaktformular Shopware Demoshop

Anrede: {sVars.anrede}
Vorname: {sVars.vorname}
Nachname: {sVars.nachname}
eMail: {sVars.email}
Telefon: {sVars.telefon}
Betreff: {sVars.betreff}
Kommentar: 
{sVars.kommentar}


',
                    'email_subject' => 'Kontaktformular Shopware',
                    'metaTitle' => null,
                    'metaDescription' => null,
                    'metaKeywords' => null,
                    'sErrors' => null,
                    'sElements' => [
                            24 => [
                                    'id' => '24',
                                    'name' => 'anrede',
                                    'note' => 'Formular Name: {$sSupport.name} und Element Typ: {$sElement.type}',
                                    'typ' => 'select',
                                    'required' => '1',
                                    'label' => 'Anrede',
                                    'class' => 'normal',
                                    'value' => 'Frau;Herr',
                                    'error_msg' => '',
                                ],
                            35 => [
                                    'id' => '35',
                                    'name' => 'vorname',
                                    'note' => 'Feld Name: {$sElement.name}',
                                    'typ' => 'text',
                                    'required' => '1',
                                    'label' => 'Vorname',
                                    'class' => 'normal',
                                    'value' => '',
                                    'error_msg' => '',
                                ],
                            36 => [
                                    'id' => '36',
                                    'name' => 'nachname',
                                    'note' => 'Formular Name: {$sSupport.name}',
                                    'typ' => 'text',
                                    'required' => '1',
                                    'label' => 'Nachname',
                                    'class' => 'normal',
                                    'value' => 'asd {$sElement.name}',
                                    'error_msg' => '',
                                ],
                            37 => [
                                    'id' => '37',
                                    'name' => 'email',
                                    'note' => 'Formular Beschreibung {$sSupport.text}',
                                    'typ' => 'email',
                                    'required' => '1',
                                    'label' => 'eMail-Adresse',
                                    'class' => 'normal',
                                    'value' => '',
                                    'error_msg' => '',
                                ],
                            38 => [
                                    'id' => '38',
                                    'name' => 'telefon',
                                    'note' => '',
                                    'typ' => 'text',
                                    'required' => '0',
                                    'label' => 'Telefon',
                                    'class' => 'normal',
                                    'value' => '',
                                    'error_msg' => '',
                                ],
                            39 => [
                                    'id' => '39',
                                    'name' => 'betreff',
                                    'note' => '',
                                    'typ' => 'text',
                                    'required' => '1',
                                    'label' => 'Betreff',
                                    'class' => 'normal',
                                    'value' => '',
                                    'error_msg' => '',
                                ],
                            40 => [
                                    'id' => '40',
                                    'name' => 'kommentar',
                                    'note' => '',
                                    'typ' => 'textarea',
                                    'required' => '1',
                                    'label' => 'Kommentar',
                                    'class' => 'normal',
                                    'value' => '',
                                    'error_msg' => '',
                                ],
                        ],
                    'sFields' => [
                            24 => '
',
                            35 => '
Vorname%*%

',
                            36 => '
asd {$sElement.name}

',
                            37 => '
eMail-Adresse%*%

',
                            38 => '
Telefon

',
                            39 => '
Betreff%*%

',
                            40 => '
Kommentar%*%

',
                        ],
                    'sLabels' => [
                            24 => 'Anrede*:
',
                            35 => 'Vorname*:
',
                            36 => 'Nachname*:
',
                            37 => 'eMail-Adresse*:
',
                            38 => 'Telefon:
',
                            39 => 'Betreff*:
',
                            40 => 'Kommentar*:
',
                        ],
                ],
            'rand' => '0d1d9cd10021eac54c3f80ac32cdb7fd',
            'success' => null,
            'theme' => [
                    'appleTouchIcon' => 'frontend/_public/src/img/apple-touch-icon-precomposed.png',
                    'desktopLogo' => 'frontend/_public/src/img/logos/logo--tablet.png',
                    'favicon' => 'frontend/_public/src/img/favicon.ico',
                    'mobileLogo' => 'frontend/_public/src/img/logos/logo--mobile.png',
                    'setPrecomposed' => true,
                    'tabletLandscapeLogo' => 'frontend/_public/src/img/logos/logo--tablet.png',
                    'tabletLogo' => 'frontend/_public/src/img/logos/logo--tablet.png',
                    'win8TileImage' => 'frontend/_public/src/img/win-tile-image.png',
                    'offcanvasCart' => true,
                    'offcanvasOverlayPage' => true,
                    'focusSearch' => false,
                    'displaySidebar' => true,
                    'sidebarFilter' => false,
                    'checkoutHeader' => true,
                    'checkoutFooter' => true,
                    'infiniteScrolling' => true,
                    'infiniteThreshold' => 4,
                    'lightboxZoomFactor' => 0,
                    'appleWebAppTitle' => '',
                    'ajaxVariantSwitch' => true,
                    'additionalCssData' => '',
                    'additionalJsLibraries' => '',
                    'brand-primary' => '#D9400B',
                    'brand-primary-light' => 'saturate(lighten(@brand-primary, 12%), 5%)',
                    'brand-secondary' => '#5F7285',
                    'brand-secondary-dark' => 'darken(@brand-secondary, 15%)',
                    'gray' => '#F5F5F8',
                    'gray-light' => 'lighten(@gray, 1%)',
                    'gray-dark' => 'darken(@gray-light, 10%)',
                    'border-color' => '@gray-dark',
                    'highlight-success' => '#2ECC71',
                    'highlight-error' => '#E74C3C',
                    'highlight-notice' => '#F1C40F',
                    'highlight-info' => '#4AA3DF',
                    'body-bg' => 'darken(@gray-light, 5%)',
                    'text-color' => '@brand-secondary',
                    'text-color-dark' => '@brand-secondary-dark',
                    'link-color' => '@brand-primary',
                    'link-hover-color' => 'darken(@link-color, 10%)',
                    'rating-star-color' => '@highlight-notice',
                    'overlay-bg' => '#000000',
                    'overlay-theme-dark-bg' => '@overlay-bg',
                    'overlay-theme-light-bg' => '#FFFFFF',
                    'overlay-opacity' => '0.7',
                    'font-base-stack' => '"Open Sans", "Helvetica Neue", Helvetica, Arial, "Lucida Grande", sans-serif;',
                    'font-headline-stack' => '@font-base-stack',
                    'font-size-base' => '14',
                    'font-base-weight' => '500',
                    'font-light-weight' => '300',
                    'font-bold-weight' => '700',
                    'font-size-h1' => '26',
                    'font-size-h2' => '21',
                    'font-size-h3' => '18',
                    'font-size-h4' => '16',
                    'font-size-h5' => '@font-size-base',
                    'font-size-h6' => '12',
                    'btn-font-size' => '14',
                    'btn-icon-size' => '10',
                    'btn-default-top-bg' => '#FFFFFF',
                    'btn-default-bottom-bg' => '@gray-light',
                    'btn-default-hover-bg' => '#FFFFFF',
                    'btn-default-text-color' => '@text-color',
                    'btn-default-hover-text-color' => '@brand-primary',
                    'btn-default-border-color' => '@border-color',
                    'btn-default-hover-border-color' => '@brand-primary',
                    'btn-primary-top-bg' => '@brand-primary-light',
                    'btn-primary-bottom-bg' => '@brand-primary',
                    'btn-primary-hover-bg' => '@brand-primary',
                    'btn-primary-text-color' => '#FFFFFF',
                    'btn-primary-hover-text-color' => '@btn-primary-text-color',
                    'btn-secondary-top-bg' => '@brand-secondary',
                    'btn-secondary-bottom-bg' => '@brand-secondary-dark',
                    'btn-secondary-hover-bg' => '@brand-secondary-dark',
                    'btn-secondary-text-color' => '#FFFFFF',
                    'btn-secondary-hover-text-color' => '@btn-secondary-text-color',
                    'panel-header-bg' => '@gray-light',
                    'panel-header-font-size' => '14',
                    'panel-header-color' => '@text-color',
                    'panel-border' => '@border-color',
                    'panel-bg' => '#FFFFFF',
                    'label-font-size' => '14',
                    'label-color' => '@text-color',
                    'input-font-size' => '14',
                    'input-bg' => '@gray-light',
                    'input-color' => '@brand-secondary',
                    'input-placeholder-color' => 'lighten(@text-color, 15%)',
                    'input-border' => '@border-color',
                    'input-focus-bg' => '#FFFFFF',
                    'input-focus-border' => '@brand-primary',
                    'input-focus-color' => '@brand-secondary',
                    'input-error-bg' => 'desaturate(lighten(@highlight-error, 38%), 20%)',
                    'input-error-border' => '@highlight-error',
                    'input-error-color' => '@highlight-error',
                    'input-success-bg' => '#FFFFFF',
                    'input-success-border' => '@highlight-success',
                    'input-success-color' => '@brand-secondary-dark',
                    'panel-table-header-bg' => '@panel-bg',
                    'panel-table-header-color' => '@text-color-dark',
                    'table-row-bg' => '#FFFFFF',
                    'table-row-color' => '@brand-secondary',
                    'table-row-highlight-bg' => 'darken(@table-row-bg, 4%)',
                    'table-header-bg' => '@brand-secondary',
                    'table-header-color' => '#FFFFFF',
                    'badge-discount-bg' => '@highlight-error',
                    'badge-discount-color' => '#FFFFFF',
                    'badge-newcomer-bg' => '@highlight-notice',
                    'badge-newcomer-color' => '#FFFFFF',
                    'badge-recommendation-bg' => '@highlight-success',
                    'badge-recommendation-color' => '#FFFFFF',
                    'badge-download-bg' => '@highlight-info',
                    'badge-download-color' => '#FFFFFF',
                ],
        ];
    }
}
