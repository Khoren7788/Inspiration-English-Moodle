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
    $answer = required_param('answer', PARAM_RAW_TRIMMED);
    if ($answer === '' || core_text::strlen($answer) > 10000) {
        throw new invalid_parameter_exception('Invalid answer');
    }
    $question = $DB->get_record('livecourse_question', ['id' => $session->currentquestionid], '*', MUST_EXIST);
    $type = $question->questiontype ?: 'multichoice';
    $iscorrect = false;
    if ($type === 'multichoice') {
        $iscorrect = in_array($answer, ['a', 'b', 'c', 'd'], true) && $answer === $question->correctoption;
    } else if ($type === 'truefalse') {
        $iscorrect = $answer === $question->answerdata;
    } else if ($type === 'matching') {
        $submitted = json_decode($answer, true);
        $expected = json_decode($question->answerdata, true);
        $iscorrect = is_array($submitted) && is_array($expected) && $submitted === $expected;
    } else {
        $normalise = static fn(string $value): string => core_text::strtolower(trim($value));
        $accepted = array_map($normalise, explode('|', $question->answerdata));
        $iscorrect = in_array($normalise($answer), $accepted, true);
    }
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
            'iscorrect' => (int) $iscorrect,
            'timeanswered' => time(),
        ]);
        livecourse_publish_event($cm->id);
    }
}

$listconditions = ['livecourseid' => $livecourse->id];
if (!has_capability('mod/livecourse:manage', $context)) {
    $listconditions['visible'] = 1;
}
$payload = [
    'active' => (bool) $session,
    'question' => null,
    'material' => null,
    'materials' => array_values(array_map(static fn($item): array => [
        'id' => (int) $item->id,
        'title' => format_string($item->title),
    ], $DB->get_records('livecourse_material', $listconditions, 'sortorder, id', 'id,title'))),
];
if ($session && $session->currentmaterialid) {
    $material = $DB->get_record('livecourse_material', [
        'id' => $session->currentmaterialid,
        'livecourseid' => $livecourse->id,
        'visible' => 1,
    ]);
    if ($material) {
        $description = file_rewrite_pluginfile_urls($material->description ?? '', 'pluginfile.php',
            $context->id, 'mod_livecourse', 'content', $material->id);
        $content = file_rewrite_pluginfile_urls($material->content ?? '', 'pluginfile.php',
            $context->id, 'mod_livecourse', 'content', $material->id);
        $orderedids = array_keys($DB->get_records('livecourse_material', [
            'livecourseid' => $livecourse->id,
            'visible' => 1,
        ], 'sortorder, id', 'id'));
        $position = array_search((int) $material->id, array_map('intval', $orderedids), true);
        $payload['material'] = [
            'id' => (int) $material->id,
            'title' => format_string($material->title),
            'type' => $material->materialtype,
            'description' => !empty($material->displaydescription)
                ? format_text($description, $material->descriptionformat ?? FORMAT_HTML, ['context' => $context]) : '',
            'displaytitle' => !empty($material->displaytitle),
            'content' => $material->materialtype === 'page'
                ? format_text($content, $material->contentformat ?? FORMAT_HTML, ['context' => $context])
                : '',
            'url' => $material->url,
            'embedurl' => $material->materialtype === 'video'
                ? livecourse_get_youtube_embed_url($material->url)
                : null,
            'position' => $position === false ? 1 : $position + 1,
            'total' => count($orderedids),
        ];
    }
}
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
        'type' => $question->questiontype ?: 'multichoice',
        'options' => [
            'a' => format_string($question->optiona),
            'b' => format_string($question->optionb),
            'c' => format_string($question->optionc),
            'd' => format_string($question->optiond),
        ],
        'answer' => $response ? $response->answer : null,
    ];
    if ($payload['question']['type'] === 'truefalse') {
        $payload['question']['options'] = [
            'true' => get_string('true', 'core'),
            'false' => get_string('false', 'core'),
        ];
    } else if ($payload['question']['type'] === 'matching') {
        $pairs = json_decode($question->answerdata, true) ?: [];
        $payload['question']['pairs'] = [
            'left' => array_keys($pairs),
            'right' => array_values($pairs),
        ];
        $payload['question']['options'] = [];
    } else if (!in_array($payload['question']['type'], ['multichoice', 'truefalse'], true)) {
        $payload['question']['options'] = [];
    }
    if (has_capability('mod/livecourse:manage', $context)) {
        $responses = $DB->get_records('livecourse_response', ['sessionid' => $session->id, 'questionid' => $question->id]);
        $counts = array_fill_keys(array_keys($payload['question']['options']), 0);
        foreach ($responses as $item) {
            if (isset($counts[$item->answer])) {
                $counts[$item->answer]++;
            }
        }
        $payload['question']['counts'] = $counts;
        $payload['question']['responsecount'] = count($responses);
        $payload['question']['correctcount'] = count(array_filter($responses, static fn($item) => (bool) $item->iscorrect));
    }
}

echo json_encode($payload, JSON_THROW_ON_ERROR);
