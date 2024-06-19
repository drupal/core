<?php

declare(strict_types=1);

namespace Drupal\Tests\image\Functional;

use Drupal\image\Entity\ImageStyle;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Tests flushing of image styles.
 *
 * @group image
 */
class ImageStyleFlushTest extends ImageFieldTestBase {

  use TestFileCreationTrait {
    getTestFiles as drupalGetTestFiles;
    compareFiles as drupalCompareFiles;
  }

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Given an image style and a wrapper, generate an image.
   */
  public function createSampleImage($style, $wrapper) {
    static $file;

    if (!isset($file)) {
      $files = $this->drupalGetTestFiles('image');
      $file = reset($files);
    }

    // Make sure we have an image in our wrapper testing file directory.
    $source_uri = \Drupal::service('file_system')->copy($file->uri, $wrapper . '://');
    // Build the derivative image.
    $derivative_uri = $style->buildUri($source_uri);
    $derivative = $style->createDerivative($source_uri, $derivative_uri);

    return $derivative ? $derivative_uri : FALSE;
  }

  /**
   * Count the number of images currently created for a style in a wrapper.
   */
  public function getImageCount($style, $wrapper) {
    $count = 0;
    if (is_dir($wrapper . '://styles/' . $style->id())) {
      $count = count(\Drupal::service('file_system')->scanDirectory($wrapper . '://styles/' . $style->id(), '/.*/'));
    }
    return $count;
  }

  /**
   * General test to flush a style.
   */
  public function testFlush(): void {

    // Setup a style to be created and effects to add to it.
    $style_name = $this->randomMachineName(10);
    $style_label = $this->randomString();
    $style_path = 'admin/config/media/image-styles/manage/' . $style_name;
    $effect_edits = [
      'image_resize' => [
        'data[width]' => 100,
        'data[height]' => 101,
      ],
      'image_scale' => [
        'data[width]' => 110,
        'data[height]' => 111,
        'data[upscale]' => 1,
      ],
    ];

    // Add style form.
    $edit = [
      'name' => $style_name,
      'label' => $style_label,
    ];
    $this->drupalGet('admin/config/media/image-styles/add');
    $this->submitForm($edit, 'Create new style');

    // Add each sample effect to the style.
    foreach ($effect_edits as $effect => $edit) {
      // Add the effect.
      $this->drupalGet($style_path);
      $this->submitForm(['new' => $effect], 'Add');
      if (!empty($edit)) {
        $this->submitForm($edit, 'Add effect');
      }
    }

    // Load the saved image style.
    $style = ImageStyle::load($style_name);

    // Create an image for the 'public' wrapper.
    $image_path = $this->createSampleImage($style, 'public');
    // Expecting to find 2 images, one is the sample.png image shown in
    // image style preview.
    $this->assertEquals(2, $this->getImageCount($style, 'public'), "Image style {$style->label()} image $image_path successfully generated.");

    // Create an image for the 'private' wrapper.
    $image_path = $this->createSampleImage($style, 'private');
    $this->assertEquals(1, $this->getImageCount($style, 'private'), "Image style {$style->label()} image $image_path successfully generated.");

    // Remove the 'image_scale' effect and updates the style, which in turn
    // forces an image style flush.
    $style_path = 'admin/config/media/image-styles/manage/' . $style->id();
    $uuids = [];
    foreach ($style->getEffects() as $uuid => $effect) {
      $uuids[$effect->getPluginId()] = $uuid;
    }
    $this->drupalGet($style_path . '/effects/' . $uuids['image_scale'] . '/delete');
    $this->submitForm([], 'Delete');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet($style_path);
    $this->submitForm([], 'Save');
    $this->assertSession()->statusCodeEquals(200);

    // Post flush, expected 1 image in the 'public' wrapper (sample.png).
    $this->assertEquals(1, $this->getImageCount($style, 'public'), "Image style {$style->label()} flushed correctly for public wrapper.");

    // Post flush, expected no image in the 'private' wrapper.
    $this->assertEquals(0, $this->getImageCount($style, 'private'), "Image style {$style->label()} flushed correctly for private wrapper.");

    $state = \Drupal::state();
    $state->set('image_module_test_image_style_flush.called', FALSE);
    $style->flush();
    $this->assertNull($state->get('image_module_test_image_style_flush.called'));
    $style->flush('/made/up/path');
    $this->assertSame('/made/up/path', $state->get('image_module_test_image_style_flush.called'));
  }

}
