<?php
require_once(__DIR__ . '/../../../config.php');

$id = required_param('id', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);
$cm = get_coursemodule_from_id('livecourse', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$livecourse = $DB->get_record('livecourse', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/livecourse:manage', $context);
require_sesskey();

$redirect = new moodle_url('/mod/livecourse/view.php', ['id' => $cm->id]);
$transaction = $DB->start_delegated_transaction();
$session = $DB->get_record('livecourse_session', ['livecourseid' => $livecourse->id, 'status' => 1]);

switch ($action) {
    case 'addquestion':
        $record = (object) [
            'livecourseid' => $livecourse->id,
            'questiontext' => required_param('questiontext', PARAM_TEXT),
            'optiona' => required_param('optiona', PARAM_TEXT),
            'optionb' => required_param('optionb', PARAM_TEXT),
            'optionc' => required_param('optionc', PARAM_TEXT),
            'optiond' => required_param('optiond', PARAM_TEXT),
            'correctoption' => required_param('correctoption', PARAM_ALPHA),
            'sortorder' => $DB->count_records('livecourse_question', ['livecourseid' => $livecourse->id]) + 1,
            'timecreated' => time(),
        ];
        if (!in_array($record->correctoption, ['a', 'b', 'c', 'd'], true)) {
            throw new invalid_parameter_exception('Invalid correct option');
        }
        $DB->insert_record('livecourse_question', $record);
        break;

    case 'startsession':
        if (!$session) {
            $DB->insert_record('livecourse_session', (object) [
                'livecourseid' => $livecourse->id,
                'status' => 1,
                'currentquestionid' => null,
                'startedby' => $USER->id,
                'timestarted' => time(),
                'timeended' => 0,
                'timemodified' => time(),
            ]);
        }
        break;

    case 'publish':
        $questionid = required_param('questionid', PARAM_INT);
        $question = $DB->get_record('livecourse_question', ['id' => $questionid, 'livecourseid' => $livecourse->id], '*', MUST_EXIST);
        if (!$session) {
            $session = (object) [
                'livecourseid' => $livecourse->id,
                'status' => 1,
                'currentquestionid' => $question->id,
                'startedby' => $USER->id,
                'timestarted' => time(),
                'timeended' => 0,
                'timemodified' => time(),
            ];
            $session->id = $DB->insert_record('livecourse_session', $session);
        } else {
            $session->currentquestionid = $question->id;
            $session->timemodified = time();
            $DB->update_record('livecourse_session', $session);
        }
        break;

    case 'closequestion':
        if ($session) {
            $session->currentquestionid = null;
            $session->timemodified = time();
            $DB->update_record('livecourse_session', $session);
        }
        break;

    case 'endsession':
        if ($session) {
            $session->status = 0;
            $session->currentquestionid = null;
            $session->timeended = time();
            $session->timemodified = time();
            $DB->update_record('livecourse_session', $session);
        }
        break;

    default:
        throw new invalid_parameter_exception('Unknown action');
}

$transaction->allow_commit();
redirect($redirect);

