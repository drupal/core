<?php

namespace Drupal\FunctionalTests\Routing;

use Drupal\entity_test\Entity\EntityTestRev;
use Drupal\simpletest\BlockCreationTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the revision route access check.
 *
 * @group Entity
 *
 * @runTestsInSeparateProcesses
 *
 * @preserveGlobalState disabled
 */
class RevisionRouteAccessTest extends BrowserTestBase {

  use BlockCreationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface;
   */
  protected $account;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['entity_test', 'user', 'block'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->placeBlock('local_tasks_block');
    $this->placeBlock('system_breadcrumb_block');

    $this->account = $this->drupalCreateUser([
      'administer entity_test content',
      'view test entity',
      'view all entity_test_rev revisions',
    ]);

    $this->drupalLogin($this->account);
  }

  /**
   * Test enhanced entity revision routes access.
   */
  public function testRevisionRouteAccess() {
    $entity = EntityTestRev::create([
      'name' => 'rev 1',
      'type' => 'default',
    ]);
    $entity->save();

    $revision = clone $entity;
    $revision->name->value = 'rev 2';
    $revision->setNewRevision(TRUE);
    $revision->isDefaultRevision(FALSE);
    $revision->save();

    $this->drupalGet('/entity_test_rev/1/revisions');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('Revisions');
    $edit_link = $this->getSession()->getPage()->findLink('Edit');
    $edit_link->click();
    $this->assertSession()->addressEquals('/entity_test_rev/manage/1/edit');
    // Check if we have revision tab link on edit page.
    $this->getSession()->getPage()->findLink('Revisions')->click();
    $this->assertSession()->addressEquals('/entity_test_rev/1/revisions');
    $this->drupalGet('/entity_test_rev/1/revision/2/view');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('rev 2');
  }

}
