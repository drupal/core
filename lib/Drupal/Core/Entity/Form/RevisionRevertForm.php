<?php

namespace Drupal\Core\Entity\Form;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class RevisionRevertForm extends ConfirmFormBase {

  /**
   * The entity revision.
   *
   * @var \Drupal\Core\Entity\EntityInterface|\Drupal\Core\Entity\RevisionableInterface|\Drupal\Core\Entity\RevisionLogInterface
   */
  protected $revision;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The entity bundle information.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInformation;

  /**
   * Creates a new RevisionRevertForm instance.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_information
   *   The bundle information.
   */
  public function __construct(DateFormatterInterface $date_formatter, EntityTypeBundleInfoInterface $bundle_information) {
    $this->dateFormatter = $date_formatter;
    $this->bundleInformation = $bundle_information;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'entity_revision_revert_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    if ($this->revision instanceof RevisionLogInterface) {
      return $this->t('Are you sure you want to revert to the revision from %revision-date?', ['%revision-date' => $this->dateFormatter->format($this->revision->getRevisionCreationTime())]);
    }
    return $this->t('Are you sure you want to revert the revision?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    if ($this->revision->getEntityType()->hasLinkTemplate('version-history')) {
      return $this->revision->toUrl('version-history');
    }
    return $this->revision->toUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return t('Revert');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $_entity_revision = NULL, Request $request = NULL) {
    $this->revision = $_entity_revision;
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // The revision timestamp will be updated when the revision is saved. Keep
    // the original one for the confirmation message.
    $this->revision = $this->prepareRevision($this->revision);
    if ($this->revision instanceof RevisionLogInterface) {
      $original_revision_timestamp = $this->revision->getRevisionCreationTime();

      $this->revision->setRevisionLogMessage($this->t('Copy of the revision from %date.', ['%date' => $this->dateFormatter->format($original_revision_timestamp)]));
      drupal_set_message(t('@type %title has been reverted to the revision from %revision-date.', ['@type' => $this->getBundleLabel($this->revision), '%title' => $this->revision->label(), '%revision-date' => $this->dateFormatter->format($original_revision_timestamp)]));
    }
    else {
      drupal_set_message(t('@type %title has been reverted', ['@type' => $this->getBundleLabel($this->revision), '%title' => $this->revision->label()]));
    }

    $this->revision->save();

    $this->logger('content')->notice('@type: reverted %title revision %revision.', ['@type' => $this->revision->bundle(), '%title' => $this->revision->label(), '%revision' => $this->revision->getRevisionId()]);
    $form_state->setRedirect(
      "entity.{$this->revision->getEntityTypeId()}.version_history",
      [$this->revision->getEntityTypeId() => $this->revision->id()]
    );
  }

  /**
   * Prepares a revision to be reverted.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $revision
   *   The revision to be reverted.
   *
   * @return \Drupal\Core\Entity\RevisionableInterface
   *   The prepared revision ready to be stored.
   */
  protected function prepareRevision(RevisionableInterface $revision) {
    $revision->setNewRevision();
    $revision->isDefaultRevision(TRUE);

    return $revision;
  }

  /**
   * Returns a bundle label.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $revision
   *   The entity revision.
   *
   * @return string
   */
  protected function getBundleLabel(RevisionableInterface $revision) {
    /** @var \Drupal\Core\Entity\EntityInterface|\Drupal\Core\Entity\RevisionableInterface $revision */
    $bundle_info = $this->bundleInformation->getBundleInfo($revision->getEntityTypeId());
    return $bundle_info[$revision->bundle()]['label'];
  }

}
