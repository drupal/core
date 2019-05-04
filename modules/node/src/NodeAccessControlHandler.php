<?php

namespace Drupal\node;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the access control handler for the node entity type.
 *
 * @see \Drupal\node\Entity\Node
 * @ingroup node_access
 */
class NodeAccessControlHandler extends EntityAccessControlHandler implements NodeAccessControlHandlerInterface, EntityHandlerInterface {

  /**
   * The node grant storage.
   *
   * @var \Drupal\node\NodeGrantDatabaseStorageInterface
   */
  protected $grantStorage;

  /**
   * Node entity storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

  /**
   * Constructs a NodeAccessControlHandler object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\node\NodeGrantDatabaseStorageInterface $grant_storage
   *   The node grant storage.
   * @param \Drupal\Core\Entity\EntityStorageInterface|null $nodeStorage
   *   Node entity storage.
   */
  public function __construct(EntityTypeInterface $entity_type, NodeGrantDatabaseStorageInterface $grant_storage, EntityStorageInterface $nodeStorage = NULL) {
    parent::__construct($entity_type);
    $this->grantStorage = $grant_storage;
    $this->nodeStorage = isset($nodeStorage) ? $nodeStorage : \Drupal::entityTypeManager()->getStorage('node');
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('node.grant_storage'),
      $container->get('entity_type.manager')->getStorage('node')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access(EntityInterface $entity, $operation, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $account = $this->prepareUser($account);

    if ($account->hasPermission('bypass node access')) {
      $result = AccessResult::allowed()->cachePerPermissions();
      return $return_as_object ? $result : $result->isAllowed();
    }
    if (!$account->hasPermission('access content')) {
      $result = AccessResult::forbidden("The 'access content' permission is required.")->cachePerPermissions();
      return $return_as_object ? $result : $result->isAllowed();
    }
    $result = parent::access($entity, $operation, $account, TRUE)->cachePerPermissions();

    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function createAccess($entity_bundle = NULL, AccountInterface $account = NULL, array $context = [], $return_as_object = FALSE) {
    $account = $this->prepareUser($account);

    if ($account->hasPermission('bypass node access')) {
      $result = AccessResult::allowed()->cachePerPermissions();
      return $return_as_object ? $result : $result->isAllowed();
    }
    if (!$account->hasPermission('access content')) {
      $result = AccessResult::forbidden("The 'access content' permission is required.")->cachePerPermissions();
      return $return_as_object ? $result : $result->isAllowed();
    }

    $result = parent::createAccess($entity_bundle, $account, $context, TRUE)->cachePerPermissions();
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $node, $operation, AccountInterface $account) {
    /** @var \Drupal\node\NodeInterface $node */

    // Fetch information from the node object if possible.
    $status = $node->isPublished();
    $uid = $node->getOwnerId();

    // Check if authors can view their own unpublished nodes.
    if ($operation === 'view' && !$status && $account->hasPermission('view own unpublished content') && $account->isAuthenticated() && $account->id() == $uid) {
      return AccessResult::allowed()->cachePerPermissions()->cachePerUser()->addCacheableDependency($node);
    }

    // Revision operations.
    // Map of operations to fallback operation and permission operation.
    $revisionPermissionOperations = [
      'view all revisions' => 'view',
      'view revision' => 'view',
      'revert' => 'revert',
      'delete revision' => 'delete',
    ];
    if (isset($revisionPermissionOperations[$operation])) {
      $revisionPermissionOperation = $revisionPermissionOperations[$operation];
      $bundle = $node->bundle();
      // If user doesn't have any of these then quit.
      if (!$account->hasPermission("$revisionPermissionOperation all revisions") && !$account->hasPermission("$revisionPermissionOperation $bundle revisions") && !$account->hasPermission('administer nodes')) {
        return AccessResult::neutral();
      }

      // If the revisions checkbox is selected for the content type, display the
      // revisions tab.
      if ($operation === 'view all revisions' && $node->type->entity->shouldCreateNewRevision()) {
        return AccessResult::allowed();
      }

      $fallBackOperations = [
        'view all revisions' => 'view',
        'view revision' => 'view',
        'revert' => 'update',
        'delete revision' => 'delete',
      ];
      $fallBackOperation = $fallBackOperations[$operation];

      // There should be at least two revisions. If the vid of the given node
      // and the vid of the default revision differ, then we already have two
      // different revisions so there is no need for a separate database
      // check. Also, if you try to revert to or delete the default revision,
      // that's not good.
      if ($node->isDefaultRevision() && ($this->nodeStorage->countDefaultLanguageRevisions($node) == 1 || $fallBackOperation === 'update' || $fallBackOperation === 'delete')) {
        return AccessResult::neutral();
      }
      elseif ($account->hasPermission('administer nodes')) {
        return AccessResult::allowed();
      }
      else {
        // First check the access to the default revision and finally, if the
        // node passed in is not the default revision then check access to
        // that, too.
        return AccessResult::allowedIf(
          $this->access($this->nodeStorage->load($node->id()), $fallBackOperation, $account) &&
          ($node->isDefaultRevision() || $this->access($node, $fallBackOperation, $account))
        );
      }
    }

    // Evaluate node grants.
    return $this->grantStorage->access($node, $operation, $account);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIf($account->hasPermission('create ' . $entity_bundle . ' content'))->cachePerPermissions();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkFieldAccess($operation, FieldDefinitionInterface $field_definition, AccountInterface $account, FieldItemListInterface $items = NULL) {
    // Only users with the administer nodes permission can edit administrative
    // fields.
    $administrative_fields = ['uid', 'status', 'created', 'promote', 'sticky'];
    if ($operation == 'edit' && in_array($field_definition->getName(), $administrative_fields, TRUE)) {
      return AccessResult::allowedIfHasPermission($account, 'administer nodes');
    }

    // No user can change read only fields.
    $read_only_fields = ['revision_timestamp', 'revision_uid'];
    if ($operation == 'edit' && in_array($field_definition->getName(), $read_only_fields, TRUE)) {
      return AccessResult::forbidden();
    }

    // Users have access to the revision_log field either if they have
    // administrative permissions or if the new revision option is enabled.
    if ($operation == 'edit' && $field_definition->getName() == 'revision_log') {
      if ($account->hasPermission('administer nodes')) {
        return AccessResult::allowed()->cachePerPermissions();
      }
      return AccessResult::allowedIf($items->getEntity()->type->entity->isNewRevision())->cachePerPermissions();
    }
    return parent::checkFieldAccess($operation, $field_definition, $account, $items);
  }

  /**
   * {@inheritdoc}
   */
  public function acquireGrants(NodeInterface $node) {
    $grants = $this->moduleHandler->invokeAll('node_access_records', [$node]);
    // Let modules alter the grants.
    $this->moduleHandler->alter('node_access_records', $grants, $node);
    // If no grants are set and the node is published, then use the default grant.
    if (empty($grants) && $node->isPublished()) {
      $grants[] = ['realm' => 'all', 'gid' => 0, 'grant_view' => 1, 'grant_update' => 0, 'grant_delete' => 0];
    }
    return $grants;
  }

  /**
   * {@inheritdoc}
   */
  public function writeGrants(NodeInterface $node, $delete = TRUE) {
    $grants = $this->acquireGrants($node);
    $this->grantStorage->write($node, $grants, NULL, $delete);
  }

  /**
   * {@inheritdoc}
   */
  public function writeDefaultGrant() {
    $this->grantStorage->writeDefault();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteGrants() {
    $this->grantStorage->delete();
  }

  /**
   * {@inheritdoc}
   */
  public function countGrants() {
    return $this->grantStorage->count();
  }

  /**
   * {@inheritdoc}
   */
  public function checkAllGrants(AccountInterface $account) {
    return $this->grantStorage->checkAll($account);
  }

}
