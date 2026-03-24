<?php

declare(strict_types=1);

namespace Drupal\views_test_checkboxes_theme\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Form alter hooks for views tests.
 */
class ViewsTestCheckboxesThemeHooks {

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_views_exposed_form_alter')]
  public function formViewsExposedFormAlter(&$form, FormStateInterface $form_state): void {
    if (isset($form['type'])) {
      $form['type']['#type'] = 'checkboxes';
    }
    if (isset($form['tid'])) {
      $form['tid']['#type'] = 'checkboxes';
    }
  }

}
