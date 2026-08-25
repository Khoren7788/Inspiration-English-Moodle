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

    case 'addmaterial':
        $materialtype = required_param('materialtype', PARAM_ALPHA);
        if (!in_array($materialtype, ['video', 'document', 'link'], true)) {
            throw new invalid_parameter_exception('Invalid material type');
        }
        $materialurl = required_param('materialurl', PARAM_URL);
        if (!preg_match('#^https://#i', $materialurl)) {
            throw new invalid_parameter_exception('Material URL must use HTTPS');
        }
        $DB->insert_record('livecourse_material', (object) [
            'livecourseid' => $livecourse->id,
            'title' => required_param('materialtitle', PARAM_TEXT),
            'materialtype' => $materialtype,
            'url' => $materialurl,
            'description' => optional_param('materialdescription', '', PARAM_TEXT),
            'visible' => 1,
            'sortorder' => $DB->count_records('livecourse_material', ['livecourseid' => $livecourse->id]) + 1,
            'timecreated' => time(),
        ]);
        break;

    case 'togglematerial':
        $materialid = required_param('materialid', PARAM_INT);
        $material = $DB->get_record('livecourse_material', [
            'id' => $materialid,
            'livecourseid' => $livecourse->id,
        ], '*', MUST_EXIST);
        $material->visible = $material->visible ? 0 : 1;
        $DB->update_record('livecourse_material', $material);
        break;

    case 'deletematerial':
        $materialid = required_param('materialid', PARAM_INT);
        $DB->delete_records('livecourse_material', [
            'id' => $materialid,
            'livecourseid' => $livecourse->id,
        ]);
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
livecourse_publish_event($cm->id);
redirect($redirect);
