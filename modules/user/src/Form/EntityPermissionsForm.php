<?php

namespace Drupal\user\Form;

use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\PermissionHandlerInterface;
use Drupal\user\RoleStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the permissions administration form for a bundle.
 *
 * This class handles bundles that are defined by configuration objects.
 *
 * @internal
 */
class EntityPermissionsForm extends UserPermissionsForm {

  /**
   * The configuration entity manager.
   *
   * @var \Drupal\Core\Config\ConfigManagerInterface
   */
  protected $configManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The bundle object.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $bundle;

  /**
   * Constructs a new EntityPermissionsForm.
   *
   * @param \Drupal\user\PermissionHandlerInterface $permission_handler
   *   The permission handler.
   * @param \Drupal\user\RoleStorageInterface $role_storage
   *   The role storage.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Config\ConfigManagerInterface $config_manager
   *   The configuration entity manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_extension_list
   *   The module extension list.
   */
  public function __construct(PermissionHandlerInterface $permission_handler, RoleStorageInterface $role_storage, ModuleHandlerInterface $module_handler, ConfigManagerInterface $config_manager, EntityTypeManagerInterface $entity_type_manager, ModuleExtensionList $module_extension_list) {
    parent::__construct($permission_handler, $role_storage, $module_handler, $module_extension_list);
    $this->configManager = $config_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.permissions'),
      $container->get('entity_type.manager')->getStorage('user_role'),
      $container->get('module_handler'),
      $container->get('config.manager'),
      $container->get('entity_type.manager'),
      $container->get('extension.list.module'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function permissionsByProvider(): array {
    // Get the names of all config entities that depend on $this->bundle.
    $config_name = $this->bundle->getConfigDependencyName();
    $config_entities = $this->configManager
      ->findConfigEntityDependencies('config', [$config_name]);
    $config_names = array_map(
      fn($dependent_config) => $dependent_config->getConfigDependencyName(),
      $config_entities,
    );
    $config_names[] = $config_name;

    // Find all the permissions that depend on $this->bundle.
    $permissions = $this->permissionHandler->getPermissions();
    $permissions_by_provider = [];
    foreach ($permissions as $permission_name => $permission) {
      $required_configs = $permission['dependencies']['config'] ?? [];
      if (array_intersect($required_configs, $config_names)) {
        $provider = $permission['provider'];
        $permissions_by_provider[$provider][$permission_name] = $permission;
      }
    }

    return $permissions_by_provider;
  }

  /**
   * Builds the user permissions administration form for a bundle.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $bundle_entity_type
   *   (optional) The entity type ID.
   * @param string|\Drupal\Core\Entity\EntityInterface $bundle
   *   (optional) Either the bundle name or the bundle object.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $bundle_entity_type = NULL, $bundle = NULL): array {
    // Set $this->bundle for use by ::permissionsByProvider().
    if ($bundle instanceof EntityInterface) {
      $this->bundle = $bundle;
      return parent::buildForm($form, $form_state);
    }

    $this->bundle = $this->entityTypeManager
      ->getStorage($bundle_entity_type)
      ->load($bundle);

    return parent::buildForm($form, $form_state);
  }

}
