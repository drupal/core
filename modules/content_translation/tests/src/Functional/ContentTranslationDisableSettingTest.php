<?php

declare(strict_types=1);

namespace Drupal\Tests\content_translation\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test disabling content translation module.
 *
 * @covers \Drupal\language\Form\ContentLanguageSettingsForm
 * @covers ::_content_translation_form_language_content_settings_form_alter
 * @group content_translation
 */
class ContentTranslationDisableSettingTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_translation',
    'menu_link_content',
    'language',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that entity schemas are up-to-date after enabling translation.
   */
  public function testDisableSetting(): void {
    // Define selectors.
    $group_checkbox = 'entity_types[menu_link_content]';
    $translatable_checkbox = 'settings[menu_link_content][menu_link_content][translatable]';
    $language_alterable = 'settings[menu_link_content][menu_link_content][settings][language][language_alterable]';

    $user = $this->drupalCreateUser([
      'administer site configuration',
      'administer content translation',
      'create content translations',
      'administer languages',
    ]);
    $this->drupalLogin($user);

    $assert = $this->assertSession();

    $this->drupalGet('admin/config/regional/content-language');

    $assert->checkboxNotChecked('entity_types[menu_link_content]');

    $edit = [
      $group_checkbox => TRUE,
      $translatable_checkbox => TRUE,
      $language_alterable => TRUE,
    ];
    $this->submitForm($edit, 'Save configuration');

    $assert->statusMessageContains('Settings successfully updated.', 'status');

    $assert->checkboxChecked($group_checkbox);

    $edit = [
      $group_checkbox => FALSE,
      $translatable_checkbox => TRUE,
      $language_alterable => TRUE,
    ];
    $this->submitForm($edit, 'Save configuration');

    $assert->statusMessageContains('Settings successfully updated.', 'status');

    $assert->checkboxNotChecked($group_checkbox);
  }

}
