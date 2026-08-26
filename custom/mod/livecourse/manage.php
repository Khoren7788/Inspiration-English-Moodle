<?php
if (!empty($_REQUEST['ajax'])) {
    define('AJAX_SCRIPT', true);
}
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
$activesessions = $DB->get_records('livecourse_session', [
    'livecourseid' => $livecourse->id,
    'status' => 1,
], 'timemodified DESC, id DESC');
$session = $activesessions ? reset($activesessions) : false;
// Recover safely if two browser tabs started the same classroom concurrently.
$keptsession = false;
foreach ($activesessions as $activesession) {
    if (!$keptsession) {
        $keptsession = true;
        continue;
    }
    $activesession->status = 0;
    $activesession->currentquestionid = null;
    $activesession->currentmaterialid = null;
    $activesession->timeended = time();
    $activesession->timemodified = time();
    $DB->update_record('livecourse_session', $activesession);
}
$readmaterial = static function() use ($livecourse): stdClass {
    $materialtype = required_param('materialtype', PARAM_ALPHA);
    if (!in_array($materialtype, ['video', 'document', 'link', 'page'], true)) {
        throw new invalid_parameter_exception('Invalid material type');
    }
    $materialurl = optional_param('materialurl', '', PARAM_URL);
    if ($materialtype !== 'page' && !preg_match('#^https://#i', $materialurl)) {
        throw new invalid_parameter_exception('Material URL must use HTTPS');
    }
    $materialcontent = $materialtype === 'page' ? optional_param('materialcontent', '', PARAM_CLEANHTML) : null;
    if ($materialtype === 'page' && trim($materialcontent) === '') {
        throw new invalid_parameter_exception('Page content is required');
    }
    $title = trim(required_param('materialtitle', PARAM_TEXT));
    if ($title === '') {
        throw new invalid_parameter_exception('Material title is required');
    }
    return (object) [
        'livecourseid' => $livecourse->id,
        'title' => $title,
        'materialtype' => $materialtype,
        'url' => $materialurl,
        'description' => optional_param('materialdescription', '', PARAM_TEXT),
        'content' => $materialcontent,
    ];
};

switch ($action) {
    case 'addquestion':
        $questiontype = required_param('questiontype', PARAM_ALPHA);
        if (!in_array($questiontype, ['multichoice', 'truefalse', 'shortanswer', 'gapfill', 'matching'], true)) {
            throw new invalid_parameter_exception('Invalid question type');
        }
        $answerdata = '';
        if ($questiontype === 'multichoice') {
            $answerdata = required_param('correctoption', PARAM_ALPHA);
            if (!in_array($answerdata, ['a', 'b', 'c', 'd'], true)) {
                throw new invalid_parameter_exception('Invalid correct option');
            }
        } else if ($questiontype === 'truefalse') {
            $answerdata = required_param('truefalseanswer', PARAM_ALPHA);
            if (!in_array($answerdata, ['true', 'false'], true)) {
                throw new invalid_parameter_exception('Invalid true/false answer');
            }
        } else if ($questiontype === 'matching') {
            $lines = preg_split('/\R/', required_param('matchingpairs', PARAM_TEXT));
            $pairs = [];
            foreach ($lines as $line) {
                $parts = array_map('trim', explode('=', $line, 2));
                if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
                    $pairs[$parts[0]] = $parts[1];
                }
            }
            if (count($pairs) < 2) {
                throw new invalid_parameter_exception('At least two matching pairs are required');
            }
            $answerdata = json_encode($pairs, JSON_THROW_ON_ERROR);
        } else {
            $answerdata = required_param('textanswer', PARAM_TEXT);
        }
        $record = (object) [
            'livecourseid' => $livecourse->id,
            'questiontext' => required_param('questiontext', PARAM_TEXT),
            'questiontype' => $questiontype,
            'answerdata' => $answerdata,
            'optiona' => optional_param('optiona', '', PARAM_TEXT),
            'optionb' => optional_param('optionb', '', PARAM_TEXT),
            'optionc' => optional_param('optionc', '', PARAM_TEXT),
            'optiond' => optional_param('optiond', '', PARAM_TEXT),
            'correctoption' => $questiontype === 'multichoice' ? $answerdata : '',
            'sortorder' => $DB->count_records('livecourse_question', ['livecourseid' => $livecourse->id]) + 1,
            'timecreated' => time(),
        ];
        if ($questiontype === 'multichoice' && in_array('', [
            $record->optiona, $record->optionb, $record->optionc, $record->optiond,
        ], true)) {
            throw new invalid_parameter_exception('All four choices are required');
        }
        $DB->insert_record('livecourse_question', $record);
        break;

    case 'addmaterial':
        $material = $readmaterial();
        $material->visible = 1;
        $material->sortorder = $DB->count_records('livecourse_material', ['livecourseid' => $livecourse->id]) + 1;
        $material->timecreated = time();
        $DB->insert_record('livecourse_material', $material);
        break;

    case 'editmaterial':
        $materialid = required_param('materialid', PARAM_INT);
        $DB->get_record('livecourse_material', [
            'id' => $materialid,
            'livecourseid' => $livecourse->id,
        ], 'id', MUST_EXIST);
        $material = $readmaterial();
        $material->id = $materialid;
        $DB->update_record('livecourse_material', $material);
        break;

    case 'reordermaterials':
        $order = array_map('intval', explode(',', required_param('order', PARAM_SEQUENCE)));
        $existingids = array_map('intval', array_keys($DB->get_records(
            'livecourse_material', ['livecourseid' => $livecourse->id], '', 'id'
        )));
        $sortedorder = $order;
        $sortedexisting = $existingids;
        sort($sortedorder);
        sort($sortedexisting);
        if ($sortedorder !== $sortedexisting) {
            throw new invalid_parameter_exception('Invalid material order');
        }
        foreach ($order as $position => $materialid) {
            $DB->set_field('livecourse_material', 'sortorder', $position + 1, [
                'id' => $materialid,
                'livecourseid' => $livecourse->id,
            ]);
        }
        break;

    case 'togglematerial':
        $materialid = required_param('materialid', PARAM_INT);
        $material = $DB->get_record('livecourse_material', [
            'id' => $materialid,
            'livecourseid' => $livecourse->id,
        ], '*', MUST_EXIST);
        $material->visible = $material->visible ? 0 : 1;
        $DB->update_record('livecourse_material', $material);
        if (!$material->visible && $session && (int) $session->currentmaterialid === (int) $material->id) {
            $session->currentmaterialid = null;
            $session->timemodified = time();
            $DB->update_record('livecourse_session', $session);
        }
        break;

    case 'deletematerial':
        $materialid = required_param('materialid', PARAM_INT);
        $DB->set_field('livecourse_session', 'currentmaterialid', null, [
            'livecourseid' => $livecourse->id,
            'currentmaterialid' => $materialid,
        ]);
        get_file_storage()->delete_area_files($context->id, 'mod_livecourse', 'content', $materialid);
        $DB->delete_records('livecourse_material', [
            'id' => $materialid,
            'livecourseid' => $livecourse->id,
        ]);
        break;

    case 'showmaterial':
        $materialid = required_param('materialid', PARAM_INT);
        $material = $DB->get_record('livecourse_material', [
            'id' => $materialid,
            'livecourseid' => $livecourse->id,
        ], '*', MUST_EXIST);
        if (!$material->visible) {
            $material->visible = 1;
            $DB->update_record('livecourse_material', $material);
        }
        if (!$session) {
            $session = (object) [
                'livecourseid' => $livecourse->id,
                'status' => 1,
                'currentquestionid' => null,
                'currentmaterialid' => $material->id,
                'startedby' => $USER->id,
                'timestarted' => time(),
                'timeended' => 0,
                'timemodified' => time(),
            ];
            $session->id = $DB->insert_record('livecourse_session', $session);
        } else {
            $session->currentmaterialid = $material->id;
            $session->timemodified = time();
            $DB->update_record('livecourse_session', $session);
        }
        break;

    case 'nextmaterial':
    case 'previousmaterial':
        if ($session) {
            $materials = array_values($DB->get_records('livecourse_material', [
                'livecourseid' => $livecourse->id,
                'visible' => 1,
            ], 'sortorder, id'));
            if ($materials) {
                $currentindex = -1;
                foreach ($materials as $index => $material) {
                    if ((int) $material->id === (int) $session->currentmaterialid) {
                        $currentindex = $index;
                        break;
                    }
                }
                $offset = $action === 'nextmaterial' ? 1 : -1;
                $targetindex = max(0, min(count($materials) - 1, $currentindex + $offset));
                if ($currentindex === -1 && $action === 'previousmaterial') {
                    $targetindex = count($materials) - 1;
                }
                $session->currentmaterialid = $materials[$targetindex]->id;
                $session->timemodified = time();
                $DB->update_record('livecourse_session', $session);
            }
        }
        break;

    case 'closematerial':
        if ($session) {
            $session->currentmaterialid = null;
            $session->timemodified = time();
            $DB->update_record('livecourse_session', $session);
        }
        break;

    case 'startsession':
        if (!$session) {
            $firstmaterials = $DB->get_records('livecourse_material', [
                'livecourseid' => $livecourse->id,
                'visible' => 1,
            ], 'sortorder, id', 'id', 0, 1);
            $firstmaterial = $firstmaterials ? reset($firstmaterials) : false;
            $DB->insert_record('livecourse_session', (object) [
                'livecourseid' => $livecourse->id,
                'status' => 1,
                'currentquestionid' => null,
                'currentmaterialid' => $firstmaterial ? $firstmaterial->id : null,
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
                'currentmaterialid' => null,
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
            $session->currentmaterialid = null;
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
if (optional_param('ajax', 0, PARAM_BOOL)) {
    http_response_code(204);
    exit;
}
redirect($redirect);
