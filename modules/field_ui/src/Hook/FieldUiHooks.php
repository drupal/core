<?php

declare(strict_types=1);

namespace Drupal\field_ui\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityFormModeInterface;
use Drupal\Core\Entity\EntityViewModeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Theme\ThemePreprocess;
use Drupal\Core\Url;
use Drupal\field_ui\FieldUI;
use Drupal\field_ui\Form\FieldConfigEditForm;
use Drupal\field_ui\Form\FieldStorageConfigEditForm;
use Drupal\field_ui\Plugin\Derivative\FieldUiLocalTask;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Hook implementations for field_ui.
 *
 * This class replaces static \Drupal::* service lookups
 * with dependency injection to improve testability and
 * alignment with Drupal coding standards.
 */
class FieldUiHooks {

  use StringTranslationTrait;

  /**
   * Constructs the FieldUiHooks service.
   */
  public function __construct(
    private readonly RouteBuilderInterface $routerBuilder,
    private readonly AccountProxyInterface $currentUser,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Creates the service from the container.
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('router.builder'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      case 'help.page.field_ui':
        $output = '';
        $output .= '<h2>' . $this->t('About') . '</h2>';
        $output .= '<p>' . $this->t(
          'The Field UI module provides an administrative user interface (UI) for managing and displaying fields. For background information, see the <a href=":field">Field module help page</a>.',
          [
            ':field' => Url::fromRoute('help.page', ['name' => 'field'])->toString(),
          ]
        ) . '</p>';
        return $output;

      case 'entity.field_storage_config.collection':
        return '<p>' . $this->t('This list shows all fields currently in use for easy reference.') . '</p>';
    }

    return NULL;
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'field_ui_table' => [
        'variables' => [
          'header' => NULL,
          'rows' => NULL,
          'footer' => NULL,
          'attributes' => [],
          'caption' => NULL,
          'colgroups' => [],
          'sticky' => FALSE,
          'responsive' => TRUE,
          'empty' => '',
        ],
        'initial preprocess' => ThemePreprocess::class . ':preprocessTable',
      ],
      'form_element__new_storage_type' => [
        'base hook' => 'form_element',
        'render element' => 'element',
      ],
    ];
  }

  /**
   * Implements hook_entity_type_build().
   */
  #[Hook('entity_type_build')]
  public function entityTypeBuild(array &$entity_types): void {
    $entity_types['field_config']->setFormClass('edit', FieldConfigEditForm::class);
    $entity_types['field_storage_config']->setFormClass('edit', FieldStorageConfigEditForm::class);
  }

  /**
   * Implements hook_entity_bundle_create().
   *
   * Router rebuild is required to expose Field UI tabs
   * for the newly created bundle.
   */
  #[Hook('entity_bundle_create')]
  public function entityBundleCreate(string $entity_type, string $bundle): void {
    $this->routerBuilder->setRebuildNeeded();
  }

  /**
   * Implements hook_entity_operation().
   */
  #[Hook('entity_operation')]
  public function entityOperation(EntityInterface $entity): array {
    $operations = [];
    $entity_type = $entity->getEntityType();
    $bundle_of = $entity_type->getBundleOf();

    if (!$bundle_of) {
      return $operations;
    }

    $definition = $this->entityTypeManager->getDefinition($bundle_of);
    if (!$definition->get('field_ui_base_route')) {
      return $operations;
    }

    if ($this->currentUser->hasPermission("administer {$bundle_of} fields")) {
      $operations['manage-fields'] = [
        'title' => $this->t('Manage fields'),
        'weight' => 15,
        'url' => Url::fromRoute("entity.{$bundle_of}.field_ui_fields", [
          $entity->getEntityTypeId() => $entity->id(),
        ]),
      ];
    }

    return $operations;
  }

  /**
   * Implements hook_ENTITY_TYPE_presave().
   */
  #[Hook('entity_view_mode_presave')]
  public function entityViewModePresave(EntityViewModeInterface $view_mode): void {
    $this->routerBuilder->setRebuildNeeded();
  }

  /**
   * Implements hook_ENTITY_TYPE_presave().
   */
  #[Hook('entity_form_mode_presave')]
  public function entityFormModePresave(EntityFormModeInterface $form_mode): void {
    $this->routerBuilder->setRebuildNeeded();
  }

  /**
   * Implements hook_ENTITY_TYPE_delete().
   */
  #[Hook('entity_view_mode_delete')]
  public function entityViewModeDelete(EntityViewModeInterface $view_mode): void {
    $this->routerBuilder->setRebuildNeeded();
  }

  /**
   * Implements hook_ENTITY_TYPE_delete().
   */
  #[Hook('entity_form_mode_delete')]
  public function entityFormModeDelete(EntityFormModeInterface $form_mode): void {
    $this->routerBuilder->setRebuildNeeded();
  }

  /**
   * Implements hook_local_tasks_alter().
   */
  #[Hook('local_tasks_alter')]
  public function localTasksAlter(array &$local_tasks): void {
    // FieldUiLocalTask still requires the container at creation time.
    $local_task = FieldUiLocalTask::create(\Drupal::getContainer(), 'field_ui.fields');
    $local_task->alterLocalTasks($local_tasks);
  }

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $forms = [
      'node_type_add_form',
      'comment_type_add_form',
      'media_type_add_form',
      'block_content_type_add_form',
    ];

    if (!in_array($form_id, $forms, TRUE)) {
      return;
    }

    if ($form_state->getFormObject()->getEntity()->isNew()) {
      $form['actions']['save_continue'] = $form['actions']['submit'];
      $form['actions']['save_continue']['#value'] = $this->t('Save and manage fields');
      $form['actions']['save_continue']['#submit'][] = [$this, 'manageFieldFormSubmit'];
    }
  }

  /**
   * Submit handler for "Save and manage fields".
   */
  public function manageFieldFormSubmit(array $form, FormStateInterface $form_state): void {
    $entity = $form_state->getFormObject()->getEntity();
    $provider = $entity->getEntityType()->getProvider();

    if ($route = FieldUI::getOverviewRouteInfo($provider, $entity->id())) {
      $form_state->setRedirectUrl($route);
    }
  }

}
