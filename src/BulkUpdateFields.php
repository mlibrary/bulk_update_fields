<?php

/**
 * @file
 * Contains \Drupal\bulk_update_fields\BulkUpdateFields.
 */

namespace Drupal\bulk_update_fields;

use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Database\Database;

class BulkUpdateFields {

  public static function updateFields($entities, $fields, &$context) {
    $message = 'Updating Fields...';
    $results = array();
    foreach ($entities as $id => $entity) {
      foreach ($fields as $field_name => $field_value) {
        if ($entity->hasField($field_name)) {
          $field_value = array_filter(array_filter($field_value, "is_numeric", ARRAY_FILTER_USE_KEY));
          $entity->get($field_name)->setValue($field_value);
        }
      }
      $entity->setNewRevision();
      $entity->save();
    }
    $context['message'] = $message;
    $context['results'] = $results;
  }

  function BulkUpdateFieldsFinishedCallback($success, $results, $operations) {
    // The 'success' parameter means no fatal PHP errors were detected. All
    // other error management should be handled using 'results'.
    if ($success) {
      $message = \Drupal::translation()->formatPlural(
        count($results),
        'One operations processed.', '@count operations processed.'
      );
    }
    else {
      $message = t('Finished with an error.');
    }
    drupal_set_message($message);
  }
}
