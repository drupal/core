<?php

declare(strict_types=1);

namespace Drupal\Tests\options\FunctionalJavascript;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\field_ui\Traits\FieldUiJSTestTrait;

/**
 * Tests the Options field UI functionality.
 *
 * @group options
 * @group #slow
 */
class OptionsFieldUITest extends WebDriverTestBase {

  use FieldUiJSTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'options',
    'field_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Machine name of the created content type.
   *
   * @var string
   */
  protected $type;

  /**
   * Name of the option field.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * Admin path to manage field storage settings.
   *
   * @var string
   */
  protected $adminPath;

  /**
   * Node form path for created content type.
   *
   * @var string
   */
  protected $nodeFormPath;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create test user.
    $admin_user = $this->drupalCreateUser([
      'bypass node access',
      'administer node fields',
      'administer node display',
    ]);
    $this->drupalLogin($admin_user);

    $type = $this->drupalCreateContentType(['type' => 'plan']);
    $this->type = $type->id();
    $this->nodeFormPath = 'node/add/' . $this->type;
  }

  /**
   * Tests option types allowed values.
   *
   * @dataProvider providerTestOptionsAllowedValues
   */
  public function testOptionsAllowedValues($option_type, $options, $is_string_option, string $add_row_method): void {
    $assert = $this->assertSession();
    $this->fieldName = 'field_options_text';
    $this->createOptionsField($option_type);
    $page = $this->getSession()->getPage();

    $this->drupalGet($this->adminPath);

    $i = 0;
    $expected_rows = 1;
    $this->assertAllowValuesRowCount(1);
    foreach ($options as $option_key => $option_label) {
      $enter_element_name = $label_element_name = "field_storage[subform][settings][allowed_values][table][$i][item][label]";
      $page->fillField($label_element_name, $option_label);
      $this->assertSession()->assertWaitOnAjaxRequest();
      $key_element_name = "field_storage[subform][settings][allowed_values][table][$i][item][key]";

      // Add keys if not string option list.
      if (!$is_string_option) {
        $this->pressEnterOnElement("[name=\"$label_element_name\"]");
        // Assert that pressing enter on label field does not create the new
        // row if the key field is visible.
        $this->assertAllowValuesRowCount($expected_rows);
        $enter_element_name = $key_element_name;
        $this->assertHasFocusByAttribute('name', $key_element_name);
        $page->fillField($key_element_name, $option_key);
        $this->assertSession()->assertWaitOnAjaxRequest();
      }
      else {
        $this->assertFalse($assert->fieldExists($key_element_name)->isVisible());
      }
      switch ($add_row_method) {
        case 'Press button':
          $page->pressButton('Add another item');
          break;

        case 'Enter button':
          $button = $assert->buttonExists('Add another item');
          $this->pressEnterOnElement('[data-drupal-selector="' . $button->getAttribute('data-drupal-selector') . '"]');
          break;

        case 'Enter element':
          // If testing using the "enter" key while focused on element there a
          // few different scenarios to test.
          switch ($i) {
            case 0:
              // For string options the machine name input can be exposed which
              // will mean the label input will no longer create the next row.
              if ($is_string_option) {
                $this->exposeOptionMachineName($expected_rows);
                $this->pressEnterOnElement("[name=\"$enter_element_name\"]");
                $this->assertHasFocusByAttribute('name', $key_element_name);
                // Ensure that pressing enter while focused on the label input
                // did not create a new row if the machine name field is
                // visible.
                $this->assertAllowValuesRowCount($expected_rows);
                $enter_element_name = $key_element_name;
              }
              break;
          }
          $this->pressEnterOnElement("[name=\"$enter_element_name\"]");
          break;

        default:
          throw new \UnexpectedValueException("Unknown method $add_row_method");
      }

      $i++;
      $expected_rows++;
      $this->assertSession()->waitForElementVisible('css', "[name='field_storage[subform][settings][allowed_values][table][$i][item][label]']");
      $this->assertHasFocusByAttribute('name', "field_storage[subform][settings][allowed_values][table][$i][item][label]");
      $this->assertAllowValuesRowCount($expected_rows);

      if ($is_string_option) {
        // Expose the key input for string options for the previous row to test
        // shifting focus from the label to key inputs on the previous row by
        // pressing enter.
        $this->exposeOptionMachineName($expected_rows - 1);
      }
      // Test that pressing enter on the label input on previous row will shift
      // focus to key input of that row.
      $this->pressEnterOnElement("[name=\"$label_element_name\"]");
      $this->assertHasFocusByAttribute('name', $key_element_name);
      $this->assertAllowValuesRowCount($expected_rows);
    }
    $page->pressButton('Save');

    // Test the order of the option list on node form.
    $this->drupalGet($this->nodeFormPath);
    $this->assertNodeFormOrder(['- None -', 'First', 'Second', 'Third']);

    // Test the order of the option list on admin path.
    $this->drupalGet($this->adminPath);
    $this->assertOrder(['First', 'Second', 'Third', ''], $is_string_option);
    $drag_handle = $page->find('css', '[data-drupal-selector="edit-field-storage-subform-settings-allowed-values-table-0"] .tabledrag-handle');
    $target = $page->find('css', '[data-drupal-selector="edit-field-storage-subform-settings-allowed-values-table-2"]');

    // Change the order the items appear.
    $drag_handle->dragTo($target);
    $this->assertOrder(['Second', 'Third', 'First', ''], $is_string_option);
    $page->pressButton('Save');

    $this->drupalGet($this->nodeFormPath);
    $this->assertNodeFormOrder(['- None -', 'Second', 'Third', 'First']);

    $this->drupalGet($this->adminPath);

    // Confirm the change in order was saved.
    $this->assertOrder(['Second', 'Third', 'First', ''], $is_string_option);

    // Delete an item.
    $page->pressButton('remove_row_button__1');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertOrder(['Second', 'First', ''], $is_string_option);
    $page->pressButton('Save');

    $this->drupalGet($this->nodeFormPath);
    $this->assertNodeFormOrder(['- None -', 'Second', 'First']);

    $this->drupalGet($this->adminPath);

    // Confirm the item removal was saved.
    $this->assertOrder(['Second', 'First', ''], $is_string_option);
  }

  /**
   * Tests that the allowed options are available to the default value widget.
   */
  public function testDefaultValueOptions(): void {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    $bundle_path = 'admin/structure/types/manage/' . $this->type;
    // Create a field of type list:string.
    $this->fieldUIAddNewFieldJS($bundle_path, 'test_string_list', 'Test string list', 'list_string', FALSE);
    $page->findField('field_storage[subform][settings][allowed_values][table][0][item][label]')->setValue('first');
    $assert_session->assertWaitOnAjaxRequest();
    $page->findField('set_default_value')->setValue(TRUE);
    // Assert that the option added in the subform is available to the default
    // value field.
    $this->assertSession()->optionExists('default_value_input[field_test_string_list]', 'first');
    $page->pressButton('Add another item');
    $this->assertNotNull($assert_session->waitForElement('css', "[name='field_storage[subform][settings][allowed_values][table][1][item][label]']"));
    $page->findField('field_storage[subform][settings][allowed_values][table][1][item][label]')->setValue('second');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->optionExists('default_value_input[field_test_string_list]', 'second');
    $page->selectFieldOption('default_value_input[field_test_string_list]', 'second');
    $page->pressButton('Save settings');
    $assert_session->pageTextContains('Saved Test string list configuration.');

    // Create a field of type list:integer.
    $this->fieldUIAddNewFieldJS($bundle_path, 'test_int_list', 'Test int list', 'list_integer', FALSE);
    $page->findField('field_storage[subform][settings][allowed_values][table][0][item][label]')->setValue('first');
    $assert_session->assertWaitOnAjaxRequest();
    // Assert that no validation is performed.
    $assert_session->statusMessageNotContains('Value field is required.');
    $page->findField('field_storage[subform][settings][allowed_values][table][0][item][key]')->setValue(1);
    $assert_session->assertWaitOnAjaxRequest();
    $page->findField('set_default_value')->setValue(TRUE);
    // Assert that the option added in the subform is available to the default
    // value field.
    $this->assertSession()->optionExists('default_value_input[field_test_int_list]', 'first');
    $page->selectFieldOption('default_value_input[field_test_int_list]', 'first');
    $page->pressButton('Save settings');
    $assert_session->pageTextContains('Saved Test int list configuration.');
  }

  /**
   * Asserts the order of provided option list on admin path.
   *
   * @param array $expected
   *   Expected order.
   * @param bool $is_string_option
   *   Whether the request is for string option list.
   */
  protected function assertOrder($expected, $is_string_option) {
    $page = $this->getSession()->getPage();
    if ($is_string_option) {
      $inputs = $page->findAll('css', '.draggable .form-text.machine-name-source');
    }
    else {
      $inputs = $page->findAll('css', '.draggable .form-text');
    }
    foreach ($expected as $step => $expected_input_value) {
      $value = $inputs[$step]->getValue();
      $this->assertSame($expected_input_value, $value, "Item $step should be $expected_input_value, but got $value");
    }
  }

  /**
   * Asserts the order of provided option list on node form.
   *
   * @param array $expected
   *   Expected order.
   */
  protected function assertNodeFormOrder($expected) {
    $elements = $this->assertSession()->selectExists('field_options_text')->findAll('css', 'option');
    $elements = array_map(function ($element) {
      return $element->getText();
    }, $elements);
    $this->assertSame($expected, $elements);
  }

  /**
   * Helper function to create list field of a given type.
   *
   * @param string $type
   *   One of 'list_integer', 'list_float' or 'list_string'.
   */
  protected function createOptionsField($type) {
    // Create a field.
    FieldStorageConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => 'node',
      'type' => $type,
    ])->save();
    FieldConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => 'node',
      'bundle' => $this->type,
    ])->save();

    \Drupal::service('entity_display.repository')
      ->getFormDisplay('node', $this->type)
      ->setComponent($this->fieldName)
      ->save();

    $this->adminPath = 'admin/structure/types/manage/' . $this->type . '/fields/node.' . $this->type . '.' . $this->fieldName;
  }

  /**
   * Presses "Enter" on the specified element.
   *
   * @param string $selector
   *   Current element having focus.
   */
  private function pressEnterOnElement(string $selector): void {
    $javascript = <<<JS
      const element = document.querySelector('$selector');
      const event = new KeyboardEvent('keypress', { key: 'Enter', keyCode: 13, bubbles: true });
      element.dispatchEvent(event);
JS;
    $this->getSession()->executeScript($javascript);
  }

  /**
   * Data provider for testOptionsAllowedValues().
   *
   * @return array
   *   Array of arrays with the following elements:
   *   - Option type.
   *   - Array of option type values.
   *   - Whether option type is string type or not.
   *   - The method which should be used to add another row to the table. The
   *     possible values are 'Press button', 'Enter button' or 'Enter element'.
   */
  public static function providerTestOptionsAllowedValues() {
    $type_cases = [
      'List integer' => [
        'list_integer',
        [1 => 'First', 2 => 'Second', 3 => 'Third'],
        FALSE,
      ],
      'List float' => [
        'list_float',
        ['0.1' => 'First', '0.2' => 'Second', '0.3' => 'Third'],
        FALSE,
      ],
      'List string' => [
        'list_string',
        ['first' => 'First', 'second' => 'Second', 'third' => 'Third'],
        TRUE,
      ],
    ];
    // Test adding options for each option field type using several possible
    // methods that could be used for navigating the options list:
    // - Press button: add a new item by pressing the 'Add another item'
    // button using mouse.
    // - Enter button: add a new item by pressing the 'Add another item'
    // button using enter key on the keyboard.
    // - Enter element: add a new item by pressing enter on the last text
    // field inside the table.
    $test_cases = [];
    foreach ($type_cases as $key => $type_case) {
      foreach (['Press button', 'Enter button', 'Enter element'] as $add_more_method) {
        $test_cases["$key: $add_more_method"] = array_merge($type_case, [$add_more_method]);
      }
    }
    return $test_cases;
  }

  /**
   * Tests `list_string` machine name with special characters.
   */
  public function testMachineNameSpecialCharacters(): void {
    $this->fieldName = 'field_options_text';
    $this->createOptionsField('list_string');
    $this->drupalGet($this->adminPath);

    $label_element_name = "field_storage[subform][settings][allowed_values][table][0][item][label]";
    $this->getSession()->getPage()->fillField($label_element_name, 'Hello world');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->exposeOptionMachineName(1);

    $key_element_name = "field_storage[subform][settings][allowed_values][table][0][item][key]";

    // Ensure that the machine name was generated correctly.
    $this->assertSession()->fieldValueEquals($key_element_name, 'hello_world');

    // Ensure that the machine name can be overridden with a value that includes
    // special characters.
    $this->getSession()->getPage()->fillField($key_element_name, '.hello #world');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->pressButton('Save settings');
    $this->assertSession()->statusMessageContains("Saved {$this->fieldName} configuration.");

    // Ensure that the machine name was saved correctly.
    $allowed_values = FieldStorageConfig::loadByName('node', $this->fieldName)
      ->getSetting('allowed_values');
    $this->assertSame(['.hello #world'], array_keys($allowed_values));
  }

  /**
   * Assert the count of the allowed values rows.
   *
   * @param int $expected_count
   *   The expected row count.
   */
  private function assertAllowValuesRowCount(int $expected_count): void {
    $this->assertCount(
      $expected_count,
      $this->getSession()->getPage()->findAll('css', '#allowed-values-order tr.draggable')
    );
  }

  /**
   * Asserts an element specified by an attribute value has focus.
   *
   * @param string $name
   *   The attribute name.
   * @param string $value
   *   The attribute value.
   *
   * @todo Replace with assertHasFocus() in https://drupal.org/i/3041768.
   */
  private function assertHasFocusByAttribute(string $name, string $value): void {
    $active_element = $this->getSession()->evaluateScript('document.activeElement');
    $this->assertSame($value, $active_element->attribute($name));
  }

  /**
   * Exposes the machine name input for a row.
   *
   * @param int $row
   *   The row number.
   */
  private function exposeOptionMachineName(int $row): void {
    $index = $row - 1;
    $rows = $this->getSession()->getPage()->findAll('css', '#allowed-values-order tr.draggable');
    $this->assertSession()->buttonExists('Edit', $rows[$index])->click();
    $this->assertSession()->waitForElementVisible('css', "[name='field_storage[subform][settings][allowed_values][table][$index][item][key]']");
  }

}
