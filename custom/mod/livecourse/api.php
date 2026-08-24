<?php
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

$id = required_param('id', PARAM_INT);
$action = optional_param('action', 'state', PARAM_ALPHA);
$cm = get_coursemodule_from_id('livecourse', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$livecourse = $DB->get_record('livecourse', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/livecourse:view', $context);

header('Content-Type: application/json; charset=utf-8');
$session = $DB->get_record('livecourse_session', ['livecourseid' => $livecourse->id, 'status' => 1]);

if ($action === 'respond') {
    require_sesskey();
    if (!$session || !$session->currentquestionid) {
        throw new moodle_exception('noactivequestion', 'mod_livecourse');
    }
    $answer = required_param('answer', PARAM_ALPHA);
    if (!in_array($answer, ['a', 'b', 'c', 'd'], true)) {
        throw new invalid_parameter_exception('Invalid answer');
    }
    $question = $DB->get_record('livecourse_question', ['id' => $session->currentquestionid], '*', MUST_EXIST);
    $existing = $DB->get_record('livecourse_response', [
        'sessionid' => $session->id,
        'questionid' => $question->id,
        'userid' => $USER->id,
    ]);
    if (!$existing) {
        $DB->insert_record('livecourse_response', (object) [
            'sessionid' => $session->id,
            'questionid' => $question->id,
            'userid' => $USER->id,
            'answer' => $answer,
            'iscorrect' => (int) ($answer === $question->correctoption),
            'timeanswered' => time(),
        ]);
    }
}

$payload = ['active' => (bool) $session, 'question' => null];
if ($session && $session->currentquestionid) {
    $question = $DB->get_record('livecourse_question', ['id' => $session->currentquestionid], '*', MUST_EXIST);
    $response = $DB->get_record('livecourse_response', [
        'sessionid' => $session->id,
        'questionid' => $question->id,
        'userid' => $USER->id,
    ]);
    $payload['question'] = [
        'id' => (int) $question->id,
        'text' => format_string($question->questiontext),
        'options' => [
            'a' => format_string($question->optiona),
            'b' => format_string($question->optionb),
            'c' => format_string($question->optionc),
            'd' => format_string($question->optiond),
        ],
        'answer' => $response ? $response->answer : null,
    ];
    if (has_capability('mod/livecourse:manage', $context)) {
        $counts = ['a' => 0, 'b' => 0, 'c' => 0, 'd' => 0];
        foreach ($DB->get_records('livecourse_response', ['sessionid' => $session->id, 'questionid' => $question->id]) as $item) {
            if (isset($counts[$item->answer])) {
                $counts[$item->answer]++;
            }
        }
        $payload['question']['counts'] = $counts;
        $payload['question']['correctoption'] = $question->correctoption;
    }
}

echo json_encode($payload, JSON_THROW_ON_ERROR);

