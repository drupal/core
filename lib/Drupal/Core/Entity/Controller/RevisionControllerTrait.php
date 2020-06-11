<?php

namespace Drupal\Core\Entity\Controller;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines a trait for common revision UI functionality.
 */
trait RevisionControllerTrait {

  /**
   * Returns the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  abstract protected function entityTypeManager();

  /**
   * Returns the langauge manager.
   *
   * @return \Drupal\Core\Language\LanguageManagerInterface
   */
  public abstract function languageManager();

  /**
   * Determines if the user has permission to revert revisions.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check revert access for.
   *
   * @return bool
   *   TRUE if the user has revert access.
   */
  abstract protected function hasRevertRevisionAccess(EntityInterface $entity);

  /**
   * Determines if the user has permission to delete revisions.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check delete revision access for.
   *
   * @return bool
   *   TRUE if the user has delete revision access.
   */
  abstract protected function hasDeleteRevisionAccess(EntityInterface $entity);

  /**
   * Builds a link to revert an entity revision.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity_revision
   *   The entity to build a revert revision link for.
   *
   * @return array
   *   A link render array.
   *
   */
  abstract protected function buildRevertRevisionLink(EntityInterface $entity_revision);

  /**
   * Builds a link to delete an entity revision.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity_revision
   *   The entity to build a delete revision link for.
   *
   * @return array
   *   A link render array.
   */
  abstract protected function buildDeleteRevisionLink(EntityInterface $entity_revision);

  /**
   * Returns a string providing details of the revision.
   *
   * E.g. Node describes its revisions using {date} by {username}. For the
   *   non-current revision, it also provides a link to view that revision.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $revision
   *   The entity revision.
   * @param bool $is_current
   *   TRUE if the revision is the current revision.
   *
   * @return string
   *   Returns a string to provide the details of the revision.
   */
  abstract protected function getRevisionDescription(ContentEntityInterface $revision, $is_current = FALSE);

  /**
   * Loads all revision IDs of an entity sorted by revision ID descending.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return mixed[]
   */
  protected function revisionIds(ContentEntityInterface $entity) {
    $entity_type = $entity->getEntityType();
    $result = $this->entityTypeManager()->getStorage($entity_type->id())->getQuery()
      ->allRevisions()
      ->condition($entity_type->getKey('id'), $entity->id())
      ->sort($entity_type->getKey('revision'), 'DESC')
      ->execute();
    return array_keys($result);
  }

  /**
   * Generates an overview table of older revisions of an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   An entity object.
   *
   * @return array
   *   A render array.
   */
  protected function revisionOverview(ContentEntityInterface $entity) {
    $langcode = $this->languageManager()
      ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
      ->getId();
    $entity_storage = $this->entityTypeManager()
      ->getStorage($entity->getEntityTypeId());

    $header = [$this->t('Revision'), $this->t('Operations')];
    $rows = [];

    $entity_revisions = $entity_storage->loadMultipleRevisions($this->revisionIds($entity));

    foreach ($entity_revisions as $revision) {
      $row = [];
      /** @var \Drupal\Core\Entity\ContentEntityInterface $revision */
      if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)->isRevisionTranslationAffected()) {
        $row[] = $this->getRevisionDescription($revision, $revision->isDefaultRevision());

        if ($revision->isDefaultRevision()) {
          $row[] = [
            'data' => [
              '#prefix' => '<em>',
              '#markup' => $this->t('Current revision'),
              '#suffix' => '</em>',
            ],
          ];
          foreach ($row as &$current) {
            $current['class'] = ['revision-current'];
          }
        }
        else {
          $links = $this->getOperationLinks($revision);
          $row[] = [
            'data' => [
              '#type' => 'operations',
              '#links' => $links,
            ],
          ];
        }
      }

      $rows[] = $row;
    }

    $build['entity_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];

    // We have no clue about caching yet.
    $build['#cache']['max-age'] = 0;

    return $build;
  }

  /**
   * Get the links of the operations for an entity revision.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity_revision
   *   The entity to build the revision links for.
   *
   * @return array
   *   The operation links.
   */
  protected function getOperationLinks(EntityInterface $entity_revision) {
    $links = [];
    if ($this->hasRevertRevisionAccess($entity_revision)) {
      $links['revert'] = $this->buildRevertRevisionLink($entity_revision);
    }

    if ($this->hasDeleteRevisionAccess($entity_revision)) {
      $links['delete'] = $this->buildDeleteRevisionLink($entity_revision);
    }

    return array_filter($links);
  }

}
