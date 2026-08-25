<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_livecourse_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026082501) {
        $table = new xmldb_table('livecourse');
        $field = new xmldb_field('meetingenabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'introformat');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('meetingurl', XMLDB_TYPE_CHAR, '1333', null, null, null, null, 'meetingenabled');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('livecourse_material');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('livecourseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
        $table->add_field('materialtype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'link');
        $table->add_field('url', XMLDB_TYPE_CHAR, '1333', null, XMLDB_NOTNULL, null, '');
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null);
        $table->add_field('visible', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('livecourse', XMLDB_KEY_FOREIGN, ['livecourseid'], 'livecourse', ['id']);
        $table->add_index('livecourse-visible-sort', XMLDB_INDEX_NOTUNIQUE, ['livecourseid', 'visible', 'sortorder']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026082501, 'livecourse');
    }

    if ($oldversion < 2026082502) {
        $table = new xmldb_table('livecourse');
        $field = new xmldb_field('meetingurl');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        $field = new xmldb_field('meetingenabled');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026082502, 'livecourse');
    }
    return true;
}
