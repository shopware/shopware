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

namespace Shopware\Storefront\Theme;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Base class for the shopware themes.
 * Used as meta information container for a theme.
 * Contains the inheritance and config definition of a theme.
 *
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class Theme extends Bundle
{
    /**
     * Defines the parent theme
     *
     * @var null
     */
    protected $extend = null;

    /**
     * Defines the human readable theme name
     * which displayed in the backend
     *
     * @var string
     */
    protected $name = '';

    /**
     * Allows to define a description text
     * for the theme
     *
     * @var null
     */
    protected $description = null;

    /**
     * Name of the theme author.
     *
     * @var null
     */
    protected $author = null;

    /**
     * License of the theme source code.
     *
     * @var null
     */
    protected $license = null;

    /**
     * @var string
     */
    protected $path;

    /**
     * Flag for the inheritance configuration.
     * If this flag is set to true, the configuration
     * of extended themes will be copied to this theme.
     *
     * Example for inheritance config behavior:
     *
     * `Theme-A` extends `Theme-B`.
     * `Theme-B` contains a config field named `text1`.
     * `inheritanceConfig` of `Theme-A` is set to true.
     * `Theme-A` backend config form contains now the `text1` field as own field.
     * Notice: Changes of `text1` field won't be saved to `Theme-B` configuration!
     *
     * @var bool
     */
    protected $inheritanceConfig = true;

    /**
     * The javascript property allows to define .js files
     * which should be compressed into one small .js file for the frontend.
     * The shopware theme compiler expects that this files are
     * stored in the ../Themes/NAME/frontend/_public/ directory.
     *
     * @var array
     */
    protected $javascript = [];

    /**
     * The css property allows to define .css files
     * which should be compressed into one small .css file for the frontend.
     * The shopware theme compiler expects that this files are
     * stored in the ../Themes/NAME/frontend/_public/ directory.
     *
     * @var array
     */
    protected $css = [];

    /**
     * Defines if theme assets should be injected before or after plugin assets.
     * This includes template directories for template inheritance and
     * less and javascript files for the theme compiler.
     *
     * @var bool
     */
    protected $injectBeforePlugins = false;

    /**
     * Don't override this function. Used
     * from the backend template module
     * to get the template hierarchy
     *
     * @return null|string
     */
    public function getExtend(): ?string
    {
        return $this->extend;
    }

    final public function getAuthor()
    {
        return $this->author;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getLicense()
    {
        return $this->license;
    }

    /**
     * Helper function which returns the theme
     * directory name
     *
     * @return mixed
     */
    public function getTemplate()
    {
        $class = get_class($this);
        $paths = explode('\\', $class);

        return $paths[count($paths) - 2];
    }

    /**
     * Getter for the $inheritanceConfig property.
     *
     * @return bool
     */
    public function useInheritanceConfig(): bool
    {
        return $this->inheritanceConfig;
    }

    /**
     * Returns the javascript files definition.
     *
     * @return array
     */
    public function getJavascript(): array
    {
        return $this->javascript;
    }

    /**
     * Returns the css files definition.
     *
     * @return array
     */
    public function getCss(): array
    {
        return $this->css;
    }

    /**
     * Override this function to create an own theme configuration
     * Example:
     * <code>
     *  public function createConfig(Form\Container\TabContainer $container)
     *  {
     *      $tab = $this->createTab('tab_name', 'Tab title');
     *
     *      $fieldSet = $this->createFieldSet('field_set_name', 'Field set title');
     *
     *      $text = $this->createTextField('variable_name', 'Field label', 'Default value');
     *
     *      $fieldSet->addElement($text);
     *
     *      $tab->addElement($fieldSet);
     *      $container->addTab($tab);
     *  }
     * </code>
     */
    public function createConfig(Form\Container\TabContainer $container)
    {
    }

    /**
     * Each theme can implement multiple configuration sets or also named color sets.
     * The shop owner has only read access on this sets.
     * The function parameter collection can be used to add new sets.
     *
     * Example:
     *   public function createConfigSets(ArrayCollection $collection)
     *   {
     *      $set = new ConfigSet();
     *      $set->setName('Set name');
     *      $set->setDescription('Set description');
     *      $set->setValues(array(
     *          'field1' => 'field1_value',
     *          'field2' => 'field2_value'
     *      ));
     *
     *      $collection->add($set);
     *   }
     */
    public function createConfigSets(ArrayCollection $collection)
    {
    }

    /**
     * @return bool
     */
    public function injectBeforePlugins(): bool
    {
        return $this->injectBeforePlugins;
    }

    /**
     * Creates a ext js tab panel.
     *
     * @param $name
     * @param array $options
     *
     * @return Form\Container\TabContainer
     */
    protected function createTabPanel($name, array $options = []): Form\Container\TabContainer
    {
        $element = new Form\Container\TabContainer($name);
        $element->fromArray($options);

        return $element;
    }

    /**
     * Creates a ext js form field.
     *
     * @param $name
     * @param $title
     * @param array $options
     *
     * @return Form\Container\FieldSet
     */
    protected function createFieldSet($name, $title, array $options = []): Form\Container\FieldSet
    {
        $element = new Form\Container\FieldSet($name, $title);
        $element->fromArray($options);

        return $element;
    }

    /**
     * Creates a ext js container which can be used as tab panel element or
     * as normal container.
     *
     * @param $name
     * @param $title
     * @param array $options
     *
     * @return Form\Container\Tab
     */
    protected function createTab($name, $title, array $options = []): Form\Container\Tab
    {
        $element = new Form\Container\Tab($name, $title);
        $element->fromArray($options);

        return $element;
    }

    /**
     * Creates a ext js text field.
     *
     * @param $name
     * @param $label
     * @param $defaultValue
     * @param array $options
     *
     * @return Form\Field\Text
     */
    protected function createTextField($name, $label, $defaultValue, array $options = []): Form\Field\Text
    {
        $element = new Form\Field\Text($name);
        $element->fromArray($options);
        $element->setLabel($label);
        $element->setDefaultValue($defaultValue);

        return $element;
    }

    /**
     * Creates a ext js number field.
     *
     * @param $name
     * @param $label
     * @param $defaultValue
     * @param array $options
     *
     * @return Form\Field\Number
     */
    protected function createNumberField($name, $label, $defaultValue, array $options = []): Form\Field\Number
    {
        $element = new Form\Field\Number($name);
        $element->fromArray($options);
        $element->setLabel($label);
        $element->setDefaultValue($defaultValue);

        return $element;
    }

    /**
     * Creates a ext js check box field.
     *
     * @param $name
     * @param $label
     * @param $defaultValue
     * @param array $options
     *
     * @return Form\Field\Boolean
     */
    protected function createCheckboxField($name, $label, $defaultValue, array $options = []): Form\Field\Boolean
    {
        $element = new Form\Field\Boolean($name);
        $element->fromArray($options);
        $element->setLabel($label);
        $element->setDefaultValue($defaultValue);

        return $element;
    }

    /**
     * Creates a custom shopware color picker field.
     *
     * @param $name
     * @param $label
     * @param $defaultValue
     * @param array $options
     *
     * @return Form\Field\Color
     */
    protected function createColorPickerField($name, $label, $defaultValue, array $options = []): Form\Field\Color
    {
        $element = new Form\Field\Color($name);
        $element->fromArray($options);
        $element->setLabel($label);
        $element->setDefaultValue($defaultValue);

        return $element;
    }

    /**
     * Creates a ext js date field.
     *
     * @param $name
     * @param $label
     * @param $defaultValue
     * @param array $options
     *
     * @return Form\Field\Date
     */
    protected function createDateField($name, $label, $defaultValue, array $options = []): Form\Field\Date
    {
        $element = new Form\Field\Date($name);
        $element->fromArray($options);
        $element->setLabel($label);
        $element->setDefaultValue($defaultValue);

        return $element;
    }

    /**
     * Creates a ext js text field with auto suffix `em`
     *
     * @param $name
     * @param $label
     * @param $defaultValue
     * @param array $options
     *
     * @return Form\Field\Em
     */
    protected function createEmField($name, $label, $defaultValue, array $options = []): Form\Field\Em
    {
        $element = new Form\Field\Em($name);
        $element->fromArray($options);
        $element->setLabel($label);
        $element->setDefaultValue($defaultValue);

        return $element;
    }

    /**
     * Creates a single media selection field.
     *
     * @param $name
     * @param $label
     * @param $defaultValue
     * @param array $options
     *
     * @return Form\Field\Media
     */
    protected function createMediaField($name, $label, $defaultValue, array $options = []): Form\Field\Media
    {
        $element = new Form\Field\Media($name);
        $element->fromArray($options);
        $element->setLabel($label);
        $element->setDefaultValue($defaultValue);

        return $element;
    }

    /**
     * Creates a text field with an auto suffix `%`
     *
     * @param $name
     * @param $label
     * @param $defaultValue
     * @param array $options
     *
     * @return Form\Field\Percent
     */
    protected function createPercentField($name, $label, $defaultValue, array $options = []): Form\Field\Percent
    {
        $element = new Form\Field\Percent($name);
        $element->fromArray($options);
        $element->setLabel($label);
        $element->setDefaultValue($defaultValue);

        return $element;
    }

    /**
     * Creates a text field with an auto suffix `px
     *
     * @param $name
     * @param $label
     * @param $defaultValue
     * @param array $options
     *
     * @return Form\Field\Pixel
     */
    protected function createPixelField($name, $label, $defaultValue, array $options = []): Form\Field\Pixel
    {
        $element = new Form\Field\Pixel($name);
        $element->fromArray($options);
        $element->setLabel($label);
        $element->setDefaultValue($defaultValue);

        return $element;
    }

    /**
     * Creates a ext js combo box field.
     *
     * @param $name
     * @param $label
     * @param $defaultValue
     * @param array[] $store   [['text' => 'displayText', 'value'  => 10], ...]
     * @param array   $options
     *
     * @return Form\Field\Selection
     */
    protected function createSelectField($name, $label, $defaultValue, array $store, array $options = []): Form\Field\Selection
    {
        $element = new Form\Field\Selection($name, $store);
        $element->fromArray($options);
        $element->setLabel($label);
        $element->setDefaultValue($defaultValue);

        return $element;
    }

    /**
     * Creates a ext js text area field.
     *
     * @param $name
     * @param $label
     * @param $defaultValue
     * @param array $options
     *
     * @return Form\Field\TextArea
     */
    protected function createTextAreaField($name, $label, $defaultValue, array $options = []): Form\Field\TextArea
    {
        $element = new Form\Field\TextArea($name);
        $element->fromArray($options);
        $element->setLabel($label);
        $element->setDefaultValue($defaultValue);

        return $element;
    }
}
