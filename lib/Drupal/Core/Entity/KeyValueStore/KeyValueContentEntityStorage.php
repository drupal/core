<?php

namespace Drupal\Core\Entity\KeyValueStore;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityStorageInterface;

/**
 * Provides a key value backend for content entities.
 */
class KeyValueContentEntityStorage extends KeyValueEntityStorage implements ContentEntityStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function createTranslation(ContentEntityInterface $entity, $langcode, array $values = []) {
    // @todo Complete the content entity storage implementation in
    //   https://www.drupal.org/node/2618436.
  }

  /**
   * {@inheritdoc}
   */
  public function createWithSampleValues($bundle = FALSE, array $values = []) {}

  /**
   * {@inheritdoc}
   */
  public function loadMultipleRevisions(array $revision_ids) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getLatestRevisionId($entity_id) {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getLatestTranslationAffectedRevisionId($entity_id, $langcode) {
    return NULL;
  }

}
