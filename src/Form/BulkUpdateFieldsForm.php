<?php

namespace Drupal\bulk_update_fields\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\user\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
 */
class BulkUpdateFieldsForm extends FormBase implements FormInterface {

  /**
   * Set a var to make stepthrough form.
   */
  protected $step = 1;
  /**
   * Keep track of user input.
   */
  protected $userInput = [];

  /**
   * @var \Drupal\user\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * @var \Drupal\Core\Session\SessionManagerInterface
   */
  private $sessionManager;

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $currentUser;

  /**
   * Constructs a \Drupal\bulk_update_fields\Form\BulkUpdateFieldsForm.
   *
   * @param \Drupal\user\PrivateTempStoreFactory $temp_store_factory
   * @param \Drupal\Core\Session\SessionManagerInterface $session_manager
   * @param \Drupal\Core\Session\AccountInterface $current_user
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory, SessionManagerInterface $session_manager, AccountInterface $current_user) {
    $this->tempStoreFactory = $temp_store_factory;
    $this->sessionManager = $session_manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.private_tempstore'),
      $container->get('session_manager'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bulk_update_fields_form';
  }

  /**
   *
   */
  public function _updateFields() {
    $entities = $this->userInput['entities'];
    $fields = $this->userInput['fields'];
    $batch = [
      'title' => t('Updating Fields...'),
      'operations' => [
        ['\Drupal\bulk_update_fields\BulkUpdateFields::updateFields', [$entities, $fields]],
      ],
      'finished' => '\Drupal\bulk_update_fields\BulkUpdateFields::BulkUpdateFieldsFinishedCallback',
    ];
    batch_set($batch);
    return 'All fields were updated successfully';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    switch ($this->step) {
      case 1:
        $this->userInput['fields'] = array_filter($form_state->getValues()['table']);
        $form_state->setRebuild();
        break;

      case 2:
        $this->userInput['fields'] = array_merge($this->userInput['fields'], $form_state->getValues()['default_value_input']);
        $form_state->setRebuild();
        break;

      case 3:
        if (method_exists($this, '_updateFields')) {
          $return_verify = $this->_updateFields();
        }
        drupal_set_message($return_verify);
        \Drupal::service("router.builder")->rebuild();
        break;
    }
    $this->step++;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if (isset($this->form)) {
      $form = $this->form;
    }
    $form['#title'] = t('Bulk Update Fields');

    switch ($this->step) {
      case 1:
        // Retrieve IDs from the temporary storage.
        $this->userInput['entities'] = $this->tempStoreFactory
          ->get('bulk_update_fields_ids')
          ->get($this->currentUser->id());
        $options = [];
        foreach ($this->userInput['entities'] as $id => $entity) {
          $this->entity = $entity;
          $fields = $entity->getFieldDefinitions();
          foreach ($fields as $field) {
            if ($field->getFieldStorageDefinition()->isBaseField() === FALSE && !isset($options[$field->getName()])) {
              $options[$field->getName()]['field_name'] = $field->getName();
            }
          }
        }
        $header = [
          'field_name' => t('Field Name'),
        ];
        $form['#title'] .= ' - ' . t('Select Fields to Alter');
        $form['table'] = [
          '#type' => 'tableselect',
          '#header' => $header,
          '#options' => $options,
          '#empty' => t('No fields found'),
        ];
        break;

      case 2:
        foreach ($this->userInput['entities'] as $id => $entity) {
          $this->entity = $entity;
          foreach ($this->userInput['fields'] as $field_name) {
            $temp_form_element = [];
            $temp_form_state = new FormState();
            if ($field = $entity->getFieldDefinition($field_name)) {
              // TODO Dates fields are incorrect due to TODOs below.
              if ($field->getType() == 'datetime') {
                drupal_set_message('Cannot update field ' . $field_name . '. Date field types are not yet updatable.', 'error');
                continue;
              }
              // TODO I cannot figure out how to get a form element for only a field. Maybe someone else can
              // TODO Doing it this way does not allow for feild labels on textarea widgets.
              $form[$field_name] = $entity->get($field_name)->defaultValuesForm($temp_form_element, $temp_form_state);
            }
          }
        }
        $form['#title'] .= ' - ' . t('Enter New Values in Appropriate Fields');
        break;

      case 3:
        $form['#title'] .= ' - ' . t('Are you sure you want to alter ' . count($this->userInput['fields']) . ' fields on ' . count($this->userInput['entities']) . ' entities?');
        $form['actions']['submit'] = [
          '#type' => 'submit',
          '#value' => $this->t('Alter Fields'),
          '#button_type' => 'primary',
        ];
        return $form;

      break;
    }
    drupal_set_message('This module is experiemental. PLEASE do not use on production databases without prior testing and a complete database dump.', 'warning');
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Next'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // TODO.
  }

}
