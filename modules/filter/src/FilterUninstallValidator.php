<?php

namespace Drupal\filter;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleUninstallValidatorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Prevents uninstallation of modules providing used filter plugins.
 */
class FilterUninstallValidator implements ModuleUninstallValidatorInterface {

  use StringTranslationTrait;

  /**
   * The filter entity storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected $filterStorage;

  public function __construct(
    #[Autowire(service: 'plugin.manager.filter')]
    protected PluginManagerInterface $filterManager,
    EntityTypeManagerInterface $entity_type_manager,
    TranslationInterface $string_translation,
  ) {
    $this->filterStorage = $entity_type_manager->getStorage('filter_format');
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public function validate($module) {
    $reasons = [];
    // Get filter plugins supplied by this module.
    if ($filter_plugins = $this->getFilterDefinitionsByProvider($module)) {
      $used_in = [];
      // Find out if any filter formats have the plugin enabled.
      foreach ($this->getEnabledFilterFormats() as $filter_format) {
        $filters = $filter_format->filters();
        foreach ($filter_plugins as $filter_plugin) {
          if ($filters->has($filter_plugin['id']) && $filters->get($filter_plugin['id'])->status) {
            $used_in[] = $filter_format->label();
            break;
          }
        }
      }
      if (!empty($used_in)) {
        $reasons[] = $this->t('Provides a filter plugin that is in use in the following filter formats: %formats', ['%formats' => implode(', ', $used_in)]);
      }
    }
    return $reasons;
  }

  /**
   * Returns all filter definitions that are provided by the specified provider.
   *
   * @param string $provider
   *   The provider of the filters.
   *
   * @return array
   *   The filter definitions for the specified provider.
   */
  protected function getFilterDefinitionsByProvider($provider) {
    return array_filter($this->filterManager->getDefinitions(), function ($definition) use ($provider) {
      return $definition['provider'] == $provider;
    });
  }

  /**
   * Returns all enabled filter formats.
   *
   * @return \Drupal\filter\FilterFormatInterface[]
   *   An array of enabled filter formats.
   */
  protected function getEnabledFilterFormats() {
    return $this->filterStorage->loadByProperties(['status' => TRUE]);
  }

}
