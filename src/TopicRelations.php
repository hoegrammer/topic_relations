<?php

namespace Drupal\topic_relations;

class TopicRelations {

  private $topic_relations = array(
    array("base"=> "field_siblings", "opposite"=>"field_siblings"),
    array("base"=> "field_parents", "opposite"=>"field_children"),
    array("base"=> "field_children", "opposite"=>"field_parents"),
  );

  public function onInsert($term) {
    foreach($this->topic_relations as $topic_relation) {
      $target_ids = $this->getTargetIds($term, $topic_relation["base"]); 
      if ($target_ids) {
        $this->addRelationships($target_ids, $topic_relation["opposite"], $term->id());
      }
    }
  }

  public function onUpdate($term) {
    foreach($this->topic_relations as $topic_relation) {
      $new_target_ids = $this->getTargetIds($term, $topic_relation["base"]); 
      $old_target_ids = $this->getTargetIds($term->original, $topic_relation["base"]);
      $added_target_ids = array_diff($new_target_ids, $old_target_ids);
      $removed_target_ids = array_diff($old_target_ids, $new_target_ids);
      if ($added_target_ids) {
        $this->addRelationships($added_target_ids, $topic_relation["opposite"], $term->id());
      }
      
      if ($removed_target_ids) {
        $this->removeRelationships($removed_target_ids, $topic_relation["opposite"], $term->id());
      }
    }
  }

  private function getTargetIds($base_term, $field_name) {
    $target_ids = array();
    foreach($base_term->$field_name as $item) {
      // sometimes entity is null
      if ($item->entity) {
        $target_ids[] = $item->entity->id();
      }
    }
    return $target_ids;
  }

  private function addRelationships($base_ids, $field_name, $target_id) {
    foreach($base_ids as $base_id) {
      $base_term = \Drupal\taxonomy\Entity\Term::load($base_id);
      // check that the relationship does not exist already. This stops recursion
      // and goes some way towards dealing with duplicates (since Drupal doesn't check).
      if (!in_array($target_id, $this->getTargetIds($base_term, $field_name))) {
        $base_term->$field_name->appendItem($target_id);  
        $base_term->save();
      }
    }
  }

  private function removeRelationships($base_ids, $field_name, $target_id) {
    foreach($base_ids as $base_id) {
      $base_term = \Drupal\taxonomy\Entity\Term::load($base_id);
      foreach($base_term->$field_name->getValue() as $delta=>$value) {
        if ($value['target_id'] == $target_id) {
          $base_term->$field_name->removeItem($delta);
          $base_term->save();
          break;
        }
      }
    }
  }
}
