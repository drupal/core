<?php

namespace Drupal\Component\Plugin;

/**
 * Plugin interface for providing some metadata inspection.
 *
 * This interface provides some simple tools for code receiving a plugin to
 * interact with the plugin system.
 *
 * @ingroup plugin_api
 */
interface PluginInspectionInterface {

  /**
   * Gets the plugin ID of the plugin instance.
   *
   * @return string
   *   The plugin ID of the plugin instance.
   */
  public function getPluginId();

  /**
   * Gets the definition of the plugin implementation.
   *
   * @return \Drupal\Component\Plugin\Definition\PluginDefinitionInterface|array
   *   The plugin definition, as returned by the discovery object used by the
   *   plugin manager.
   */
  public function getPluginDefinition();

}
