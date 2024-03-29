<?php

namespace Drupal\views\Plugin\views\access;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsAccess;
use Symfony\Component\Routing\Route;

/**
 * Access plugin that provides no access control at all.
 *
 * @ingroup views_access_plugins
 */
#[ViewsAccess(
  id: 'none',
  title: new TranslatableMarkup('Unrestricted'),
  help: new TranslatableMarkup('Will be available to all users.'),
)]
class None extends AccessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function summaryTitle() {
    return $this->t('Unrestricted');
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    // No access control.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route) {
    $route->setRequirement('_access', 'TRUE');
  }

}
