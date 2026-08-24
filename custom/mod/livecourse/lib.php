<?php
defined('MOODLE_INTERNAL') || die();

function livecourse_supports(string $feature): ?bool {
    return match ($feature) {
        FEATURE_MOD_INTRO => true,
        FEATURE_SHOW_DESCRIPTION => true,
        FEATURE_GRADE_HAS_GRADE => false,
        FEATURE_GROUPS => false,
        FEATURE_BACKUP_MOODLE2 => false,
        default => null,
    };
}

function livecourse_add_instance(stdClass $data, mod_livecourse_mod_form $mform = null): int {
    global $DB;
    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    return $DB->insert_record('livecourse', $data);
}

function livecourse_update_instance(stdClass $data, mod_livecourse_mod_form $mform = null): bool {
    global $DB;
    $data->id = $data->instance;
    $data->timemodified = time();
    return $DB->update_record('livecourse', $data);
}

function livecourse_delete_instance(int $id): bool {
    global $DB;
    if (!$DB->record_exists('livecourse', ['id' => $id])) {
        return false;
    }
    $sessions = $DB->get_records('livecourse_session', ['livecourseid' => $id], '', 'id');
    if ($sessions) {
        $DB->delete_records_list('livecourse_response', 'sessionid', array_keys($sessions));
    }
    $DB->delete_records('livecourse_session', ['livecourseid' => $id]);
    $DB->delete_records('livecourse_question', ['livecourseid' => $id]);
    $DB->delete_records('livecourse', ['id' => $id]);
    return true;
}
