<?php

namespace Drupal\Core\Asset;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Optimizes JavaScript assets.
 */
class JsCollectionOptimizerLazy implements AssetCollectionGroupOptimizerInterface {

  use AssetGroupSetHashTrait;

  /**
   * Constructs a JsCollectionOptimizerLazy.
   *
   * @param \Drupal\Core\Asset\AssetCollectionGrouperInterface $grouper
   *   The grouper for JS assets.
   * @param \Drupal\Core\Asset\AssetOptimizerInterface $optimizer
   *   The asset optimizer.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $themeManager
   *   The theme manager.
   * @param \Drupal\Core\Asset\LibraryDependencyResolverInterface $dependencyResolver
   *   The library dependency resolver.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
   *   The file URL generator.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state key/value store.
   */
  public function __construct(
    protected readonly AssetCollectionGrouperInterface $grouper,
    protected readonly AssetOptimizerInterface $optimizer,
    protected readonly ThemeManagerInterface $themeManager,
    protected readonly LibraryDependencyResolverInterface $dependencyResolver,
    protected readonly RequestStack $requestStack,
    protected readonly FileSystemInterface $fileSystem,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly FileUrlGeneratorInterface $fileUrlGenerator,
    protected readonly TimeInterface $time,
    protected readonly LanguageManagerInterface $languageManager,
    protected readonly StateInterface $state
  ) {}

  /**
   * {@inheritdoc}
   */
  public function optimize(array $js_assets, array $libraries) {
    // File names are generated based on library/asset definitions. This
    // includes a hash of the assets and the group index. Additionally, the full
    // set of libraries, already loaded libraries and theme are sent as query
    // parameters to allow a PHP controller to generate a valid file with
    // sufficient information. Files are not generated by this method since
    // they're assumed to be successfully returned from the URL created whether
    // on disk or not.

    // Group the assets.
    $js_groups = $this->grouper->group($js_assets);

    $js_assets = [];
    foreach ($js_groups as $order => $js_group) {
      // We have to return a single asset, not a group of assets. It is now up
      // to one of the pieces of code in the switch statement below to set the
      // 'data' property to the appropriate value.
      $js_assets[$order] = $js_group;

      switch ($js_group['type']) {
        case 'file':
          // No preprocessing, single JS asset: just use the existing URI.
          if (!$js_group['preprocess']) {
            $uri = $js_group['items'][0]['data'];
            $js_assets[$order]['data'] = $uri;
          }
          else {
            // To reproduce the full context of assets outside of the request,
            // we must know the entire set of libraries used to generate all CSS
            // groups, whether or not files in a group are from a particular
            // library or not.
            $js_assets[$order]['preprocessed'] = TRUE;
          }
          break;

        case 'external':
          // We don't do any aggregation and hence also no caching for external
          // JS assets.
          $uri = $js_group['items'][0]['data'];
          $js_assets[$order]['data'] = $uri;
          break;

        case 'setting':
          $js_assets[$order]['data'] = $js_group['data'];
          break;
      }
    }
    if ($libraries) {
      // All group URLs have the same query arguments apart from the delta and
      // scope, so prepare them in advance.
      $language = $this->languageManager->getCurrentLanguage()->getId();
      $query_args = [
        'language' => $language,
        'theme' => $this->themeManager->getActiveTheme()->getName(),
        'include' => UrlHelper::compressQueryParameter(implode(',', $this->dependencyResolver->getMinimalRepresentativeSubset($libraries))),
      ];
      $ajax_page_state = $this->requestStack->getCurrentRequest()
        ->get('ajax_page_state');
      $already_loaded = isset($ajax_page_state) ? explode(',', $ajax_page_state['libraries']) : [];
      if ($already_loaded) {
        $query_args['exclude'] = UrlHelper::compressQueryParameter(implode(',', $this->dependencyResolver->getMinimalRepresentativeSubset($already_loaded)));
      }

      // Generate a URL for the group, but do not process it inline, this is
      // done by \Drupal\system\controller\JsAssetController.
      foreach ($js_assets as $order => $js_asset) {
        if (!empty($js_asset['preprocessed'])) {
          $query = [
            'scope' => $js_asset['scope'] === 'header' ? 'header' : 'footer',
            'delta' => "$order",
          ] + $query_args;
          $filename = 'js_' . $this->generateHash($js_asset) . '.js';
          $uri = 'assets://js/' . $filename;
          $js_assets[$order]['data'] = $this->fileUrlGenerator->generateAbsoluteString($uri) . '?' . UrlHelper::buildQuery($query);
        }
        unset($js_assets[$order]['items']);
      }
    }

    return $js_assets;
  }

  /**
   * {@inheritdoc}
   */
  public function getAll() {
    return $this->state->get('system.js_cache_files', []);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll() {
    $this->state->delete('system.js_cache_files');
    $delete_stale = function ($uri) {
      $threshold = $this->configFactory
        ->get('system.performance')
        ->get('stale_file_threshold');
      // Default stale file threshold is 30 days.
      if ($this->time->getRequestTime() - filemtime($uri) > $threshold) {
        $this->fileSystem->delete($uri);
      }
    };
    if (is_dir('assets://js')) {
      $this->fileSystem->scanDirectory('assets://js', '/.*/', ['callback' => $delete_stale]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function optimizeGroup(array $group): string {
    $data = '';
    $current_license = FALSE;
    foreach ($group['items'] as $js_asset) {
      // Ensure license information is available as a comment after
      // optimization.
      if ($js_asset['license'] !== $current_license) {
        $data .= "/* @license " . $js_asset['license']['name'] . " " . $js_asset['license']['url'] . " */\n";
      }
      $current_license = $js_asset['license'];
      // Optimize this JS file, but only if it's not yet minified.
      if (isset($js_asset['minified']) && $js_asset['minified']) {
        $data .= file_get_contents($js_asset['data']);
      }
      else {
        $data .= $this->optimizer->optimize($js_asset);
      }
      // Append a ';' and a newline after each JS file to prevent them from
      // running together.
      $data .= ";\n";
    }
    // Remove unwanted JS code that causes issues.
    return $this->optimizer->clean($data);
  }

}
