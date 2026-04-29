<?php

namespace Drupal\block_content;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheCollector;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * A cache collector that caches IDs for block_content UUIDs.
 *
 * As block_content entities are used as block plugin derivatives, it is a
 * fairly safe limitation that there are not hundreds of them, a site will
 * likely run into problems with too many block content entities in other places
 * than a cache that only stores UUID's and IDs. The same assumption is not true
 * for other content entities.
 *
 * @internal
 */
class BlockContentUuidLookup extends CacheCollector {

  public function __construct(
    #[Autowire(service: 'cache.bootstrap')]
    CacheBackendInterface $cache,
    #[Autowire(service: 'lock')]
    LockBackendInterface $lock,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct('block_content_uuid', $cache, $lock);
  }

  /**
   * {@inheritdoc}
   */
  protected function resolveCacheMiss($key) {
    $ids = $this->entityTypeManager->getStorage('block_content')->getQuery()
      ->accessCheck(FALSE)
      ->condition('uuid', $key)
      ->execute();

    // Only cache if there is a match, otherwise creating new entities would
    // require to invalidate the cache.
    $id = reset($ids);
    if ($id) {
      $this->storage[$key] = $id;
      $this->persist($key);
    }
    return $id;
  }

}
