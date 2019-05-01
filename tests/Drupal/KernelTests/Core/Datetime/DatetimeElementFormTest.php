<?php

namespace Drupal\KernelTests\Core\Datetime;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests DatetimeElement functionality.
 *
 * @group Form
 */
class DatetimeElementFormTest extends KernelTestBase implements FormInterface {

  /**
   * The variable under test.
   */
  protected $flag;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['datetime', 'system'];

  /**
   * Sets up the test.
   */
  protected function setUp() {
    parent::setUp();
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'test_datetime_element';
  }

  /**
   * {@inheritdoc}
   */
  public function datetimecallback($date) {
    $this->flag = 'Date time callback called.';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['datetime_element'] = [
      '#title' => 'datelist test',
      '#type' => 'datetime',
      '#default_value' => new DrupalDateTime('2000-01-01 00:00:00'),
      '#date_date_format' => ['Y-m-d'],
      '#date_time_format' => ['H:i:s'],
      '#date_date_element' => 'HTML Date',
      '#date_time_element' => 'HTML Time',
      '#date_increment' => 1,
      '#date_date_callbacks' => [[$this, 'datetimecallback']],
    ];

    // Element without specifying the default value.
    $form['simple_datetime_element'] = [
      '#type' => 'datetime',
      '#date_date_format' => ['Y-m-d'],
      '#date_time_format' => ['H:i:s'],
      '#date_date_element' => 'HTML Date',
      '#date_time_element' => 'HTML Time',
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

  /**
   * Form validation handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}

  /**
   * Tests that default handlers are added even if custom are specified.
   */
  public function testDatetimeElement() {
    $form = \Drupal::formBuilder()->getForm($this);
    $this->render($form);

    $this->assertEqual(t('Date time callback called.'), $this->flag);
  }

}
