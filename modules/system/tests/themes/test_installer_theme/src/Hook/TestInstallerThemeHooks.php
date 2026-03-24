<?php

declare(strict_types=1);

namespace Drupal\test_installer_theme\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Form alter hooks for installer tests.
 */
class TestInstallerThemeHooks {

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_install_select_language_form_alter')]
  public function formInstallSelectLanguageFormAlter(array &$form): void {
    $form['function_name']['#markup'] = 'Added by custom installer theme.';
  }

}
