<?php

/**
 * @file
 * Contains \Drupal\field_collection\Tests\FieldCollectionBasicTestCase.
 */

namespace Drupal\field_collection\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Test basics.
 */
class FieldCollectionBasicTestCase extends WebTestBase {

  /**
   * Field collection field.
   *
   * @var
   */
  protected $field;

  /**
   * Field collection field instance.
   *
   * @var
   */
  protected $instance;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = array('field_collection');

  public static function getInfo() {
    return array(
      'name' => 'Field collection',
      'description' => 'Tests creating and using field collections.',
      'group' => 'Field types',
    );
  }

  public function setUp() {
    parent::setUp();

    // Create a field_collection field to use for the tests.
    $this->field_name = 'field_test_collection';
    $this->field = entity_create('field_entity', array(
      'name' => $this->field_name,
      'type' => 'field_collection',
      'entity_type' => 'node',
    ));
    $this->field->save();

    $this->instance = entity_create('field_instance', array(
      'field_name' => $this->field_name,
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => $this->randomName() . '_label',
      'description' => $this->randomName() . '_description',
      'weight' => mt_rand(0, 127),
      'settings' => array(),
      'widget' => array(
        'type' => 'hidden',
        'label' => 'Test',
        'settings' => array(),
      ),
    ));
    $this->instance->save();

    $this->field->cardinality = 4;
    $this->field->save();
  }

  /**
   * Helper for creating a new node with a field collection item.
   */
  protected function createNodeWithFieldCollection() {
    $node = $this->drupalCreateNode(array('type' => 'article'));
    // Manually create a field_collection.
    $entity = entity_create('field_collection_item',
                            array('field_name' => $this->field_name));
    $entity->setHostEntity('node', $node);
    $entity->save();
    $node->save();

    return array($node, $entity);
  }

  /**
   * Tests CRUD.
   */
  public function testCRUD() {
    list ($node, $entity) = $this->createNodeWithFieldCollection();
    $node = node_load($node->nid->value, TRUE);

    $this->assertEqual(
      $entity->id(), $node->{$this->field_name}->value,
      'A field_collection_item has been successfully created and referenced.');

    $this->assertEqual(
      $entity->revision_id->value, $node->{$this->field_name}->revision_id,
      'The new field_collection_item has the correct revision.');

    // Test adding an additional field_collection during node edit.
    $entity2 = entity_create('field_collection_item',
                             array('field_name' => $this->field_name));

    $node->{$this->field_name}[1]->field_collection_item = $entity2;
    $node->save();
    $node = node_load($node->nid->value, TRUE);

    $this->assertTrue(
      !empty($entity2->id()) && !empty($entity2->getRevisionId()),
      'field_collection has been saved.');

    $this->assertEqual($entity->id(), $node->{$this->field_name}->value,
                       'Existing reference has been kept during update.');

    $this->assertEqual(
      $entity->getRevisionId(),
      $node->{$this->field_name}[0]->revision_id,
      'Revision: Existing reference has been kept during update.');

    $this->assertEqual($entity2->id(), $node->{$this->field_name}[1]->value,
                       'New field_collection has been properly referenced.');

    $this->assertEqual(
      $entity2->getRevisionId(),
      $node->{$this->field_name}[1]->revision_id,
      'Revision: New field_collection has been properly referenced.');

    // Make sure deleting the field_collection removes the reference.
    $entity2->delete();
    $node = node_load($node->nid->value, TRUE);
    $this->assertTrue(!isset($node->{$this->field_name}[1]),
                      'Reference correctly deleted.');

    // Make sure field_collections are removed during deletion of the host.
    $node->delete();
    $this->assertTrue(
      entity_load_multiple('field_collection_item', NULL, TRUE) === array(),
      'Field collections are deleted when the host is deleted.');

    // Try deleting nodes with collections without any values.
    $node = $this->drupalCreateNode(array('type' => 'article'));
    $node->delete();
    $this->assertTrue(node_load($node->nid->value, NULL, TRUE) == FALSE,
                      'Node without collection values deleted.');

    // Test creating a field collection entity with a not-yet saved host entity.
    $node = entity_create('node', array('type' => 'article'));
    $entity = entity_create('field_collection_item',
                            array('field_name' => $this->field_name));
    $entity->setHostEntity('node', $node);
    $entity->save();

    // Now the node should have been saved with the collection and the link
    // should have been established.
    $this->assertTrue(!empty($node->nid->value),
                      'Node has been saved with the collection.');

    $this->assertTrue(
      count($node->{$this->field_name}) == 1 &&
      !empty($node->{$this->field_name}[0]->value) &&
      !empty($node->{$this->field_name}[0]->revision_id),
      'Link has been established.');

    // Again, test creating a field collection with a not-yet saved host entity,
    // but this time save both entities via the host.
    $node = entity_create('node', array('type' => 'article'));
    $entity = entity_create('field_collection_item',
                            array('field_name' => $this->field_name));
    $entity->setHostEntity('node', $node);
    $node->save();

    $this->assertTrue(!empty($entity->id()) && !empty($entity->getRevisionId()),
                      'Collection has been saved with the host.');

    $this->assertTrue(
      count($node->{$this->field_name}) == 1 &&
      !empty($node->{$this->field_name}[0]->value) &&
      !empty($node->{$this->field_name}[0]->revision_id),
      'Link has been established.');

    /*
    // Test Revisions.
    list ($node, $item) = $this->createNodeWithFieldCollection();

    // Test saving a new revision of a node.
    $node->revision = TRUE;
    node_save($node);
    $item_updated = field_collection_item_load($node->{$this->field_name}[LANGUAGE_NONE][0]['value']);
    $this->assertNotEqual($item->revision_id, $item_updated->revision_id, 'Creating a new host entity revision creates a new field collection revision.');

    // Test saving the node without creating a new revision.
    $item = $item_updated;
    $node->revision = FALSE;
    node_save($node);
    $item_updated = field_collection_item_load($node->{$this->field_name}[LANGUAGE_NONE][0]['value']);
    $this->assertEqual($item->revision_id, $item_updated->revision_id, 'Updating a new host entity  without creating a new revision does not create a new field collection revision.');

    // Create a new revision of the node, such we have a non default node and
    // field collection revision. Then test using it.
    $vid = $node->vid;
    $item_revision_id = $item_updated->revision_id;
    $node->revision = TRUE;
    node_save($node);

    $item_updated = field_collection_item_load($node->{$this->field_name}[LANGUAGE_NONE][0]['value']);
    $this->assertNotEqual($item_revision_id, $item_updated->revision_id, 'Creating a new host entity revision creates a new field collection revision.');
    $this->assertTrue($item_updated->isDefaultRevision(), 'Field collection of default host entity revision is default too.');
    $this->assertEqual($item_updated->hostEntityId(), $node->nid, 'Can access host entity ID of default field collection revision.');
    $this->assertEqual($item_updated->hostEntity()->vid, $node->vid, 'Loaded default host entity revision.');

    $item = entity_revision_load('field_collection_item', $item_revision_id);
    $this->assertFalse($item->isDefaultRevision(), 'Field collection of non-default host entity is non-default too.');
    $this->assertEqual($item->hostEntityId(), $node->nid, 'Can access host entity ID of non-default field collection revision.');
    $this->assertEqual($item->hostEntity()->vid, $vid, 'Loaded non-default host entity revision.');

    // Delete the non-default revision and make sure the field collection item
    // revision has been deleted too.
    entity_revision_delete('node', $vid);
    $this->assertFalse(entity_revision_load('node', $vid), 'Host entity revision deleted.');
    $this->assertFalse(entity_revision_load('field_collection_item', $item_revision_id), 'Field collection item revision deleted.');

    // Test having archived field collections, i.e. collections referenced only
    // in non-default revisions.
    list ($node, $item) = $this->createNodeWithFieldCollection();
    // Create two revisions.
    $node_vid = $node->vid;
    $node->revision = TRUE;
    node_save($node);

    $node_vid2 = $node->vid;
    $node->revision = TRUE;
    node_save($node);

    // Now delete the field collection item for the default revision.
    $item = field_collection_item_load($node->{$this->field_name}[LANGUAGE_NONE][0]['value']);
    $item_revision_id = $item->revision_id;
    $item->deleteRevision();
    $node = node_load($node->nid);
    $this->assertTrue(!isset($node->{$this->field_name}[LANGUAGE_NONE][0]), 'Field collection item revision removed from host.');
    $this->assertFalse(field_collection_item_revision_load($item->revision_id), 'Field collection item default revision deleted.');

    $item = field_collection_item_load($item->item_id);
    $this->assertNotEqual($item->revision_id, $item_revision_id, 'Field collection default revision has been updated.');
    $this->assertTrue($item->archived, 'Field collection item has been archived.');
    $this->assertFalse($item->isInUse(), 'Field collection item specified as not in use.');
    $this->assertTrue($item->isDefaultRevision(), 'Field collection of non-default host entity is default (but archived).');
    $this->assertEqual($item->hostEntityId(), $node->nid, 'Can access host entity ID of non-default field collection revision.');
    $this->assertEqual($item->hostEntity()->nid, $node->nid, 'Loaded non-default host entity revision.');

    // Test deleting a revision of an archived field collection.
    $node_revision2 = node_load($node->nid, $node_vid2);
    $item = field_collection_item_revision_load($node_revision2->{$this->field_name}[LANGUAGE_NONE][0]['revision_id']);
    $item->deleteRevision();
    // There should be one revision left, so the item should still exist.
    $item = field_collection_item_load($item->item_id);
    $this->assertTrue($item->archived, 'Field collection item is still archived.');
    $this->assertFalse($item->isInUse(), 'Field collection item specified as not in use.');

    // Test that deleting the first node revision deletes the whole field
    // collection item as it contains its last revision.
    node_revision_delete($node_vid);
    $this->assertFalse(field_collection_item_load($item->item_id), 'Archived field collection deleted when last revision deleted.');

    // Test that removing a field-collection item also deletes it.
    list ($node, $item) = $this->createNodeWithFieldCollection();

    $node->{$this->field_name}[LANGUAGE_NONE] = array();
    $node->revision = FALSE;
    node_save($node);
    $this->assertFalse(field_collection_item_load($item->item_id), 'Removed field collection item has been deleted.');

    // Test removing a field-collection item while creating a new host revision.
    list ($node, $item) = $this->createNodeWithFieldCollection();
    $node->{$this->field_name}[LANGUAGE_NONE] = array();
    $node->revision = TRUE;
    node_save($node);
    // Item should not be deleted but archived now.
    $item = field_collection_item_load($item->item_id);
    $this->assertTrue($item, 'Removed field collection item still exists.');
    $this->assertTrue($item->archived, 'Removed field collection item is archived.');
    */
  }

  /**
   * Make sure the basic UI and access checks are working.
   */
  public function testBasicUI() {
    // Add a field to the collection.
    $field = entity_create('field_entity', array(
      'name' => 'field_text',
      'entity_type' => 'field_collection_item',
      'type' => 'text',
    ));
    $field->save();

    entity_create('field_instance', array(
      'field_name' => $field->name,
      'entity_type' => 'field_collection_item',
      'bundle' => $this->field_name,
      /*'label' => 'Test text field',
      'widget' => array(
        'type' => 'text_textfield',
      ),*/
    ))->save();


    $user = $this->drupalCreateUser();
    $node = $this->drupalCreateNode(array('type' => 'article'));

    $this->drupalLogin($user);
    /*
    // Make sure access is denied.
    $path = 'field-collection/field-test-collection/add/node/' . $node->nid;
    $this->drupalGet($path);
    $this->assertText(t('Access denied'), 'Access has been denied.');

    $user_privileged = $this->drupalCreateUser(array('access content', 'edit any article content'));
    $this->drupalLogin($user_privileged);
    $this->drupalGet("node/$node->nid");
    $this->assertLinkByHref($path, 0, 'Add link is shown.');
    $this->drupalGet($path);
    $this->assertText(t('Test text field'), 'Add form is shown.');

    $edit['field_text[und][0][value]'] = $this->randomName();
    $this->drupalPost($path, $edit, t('Save'));
    $this->assertText(t('The changes have been saved.'), 'Field collection saved.');

    $this->assertText($edit['field_text[und][0][value]'], "Added field value is shown.");

    $edit['field_text[und][0][value]'] = $this->randomName();
    $this->drupalPost('field-collection/field-test-collection/1/edit', $edit, t('Save'));
    $this->assertText(t('The changes have been saved.'), 'Field collection saved.');
    $this->assertText($edit['field_text[und][0][value]'], "Field collection has been edited.");

    $this->drupalGet('field-collection/field-test-collection/1');
    $this->assertText($edit['field_text[und][0][value]'], "Field collection can be viewed.");

    // Add further 3 items, so we have reached 4 == maxium cardinality.
    $this->drupalPost($path, $edit, t('Save'));
    $this->drupalPost($path, $edit, t('Save'));
    $this->drupalPost($path, $edit, t('Save'));
    // Make sure adding doesn't work any more as we have restricted cardinality
    // to 1.
    $this->drupalGet($path);
    $this->assertText(t('Too many items.'), 'Maxium cardinality has been reached.');

    $this->drupalPost('field-collection/field-test-collection/1/delete', array(), t('Delete'));
    $this->drupalGet($path);
    // Add form is shown again.
    $this->assertText(t('Test text field'), 'Field collection item has been deleted.');

    // Test the viewing a revision. There should be no links to change it.
    $vid = $node->vid;
    $node = node_load($node->nid, NULL, TRUE);
    $node->revision = TRUE;
    node_save($node);

    $this->drupalGet("node/$node->nid/revisions/$vid/view");
    $this->assertResponse(403, 'Access to view revision denied');
    // Login in as admin and try again.
    $user = $this->drupalCreateUser(array('administer nodes', 'bypass node access'));
    $this->drupalLogin($user);
    $this->drupalGet("node/$node->nid/revisions/$vid/view");
    $this->assertNoResponse(403, 'Access to view revision granted');

    $this->assertNoLinkByHref($path, 'No links on revision view.');
    $this->assertNoLinkByHref('field-collection/field-test-collection/2/edit', 'No links on revision view.');
    $this->assertNoLinkByHref('field-collection/field-test-collection/2/delete', 'No links on revision view.');

    $this->drupalGet("node/$node->nid/revisions");
  */
  }
}