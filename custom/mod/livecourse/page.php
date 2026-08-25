<?php
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/page_form.php');

$id = required_param('id', PARAM_INT);
$materialid = optional_param('materialid', 0, PARAM_INT);
$cm = get_coursemodule_from_id('livecourse', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$livecourse = $DB->get_record('livecourse', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/livecourse:manage', $context);

$material = null;
if ($materialid) {
    $material = $DB->get_record('livecourse_material', [
        'id' => $materialid,
        'livecourseid' => $livecourse->id,
        'materialtype' => 'page',
    ], '*', MUST_EXIST);
}

$PAGE->set_url('/mod/livecourse/page.php', ['id' => $cm->id, 'materialid' => $materialid]);
$PAGE->set_title(get_string($material ? 'editcontentpage' : 'addcontentpage', 'mod_livecourse'));
$PAGE->set_heading(format_string($livecourse->name));

$editoroptions = [
    'maxfiles' => -1,
    'maxbytes' => $course->maxbytes,
    'trusttext' => true,
    'context' => $context,
    'subdirs' => true,
];
$form = new livecourse_page_form(null, [
    'cmid' => $cm->id,
    'materialid' => $materialid,
    'context' => $context,
    'editoroptions' => $editoroptions,
]);

$returnurl = new moodle_url('/mod/livecourse/view.php', ['id' => $cm->id]);
if ($form->is_cancelled()) {
    redirect($returnurl);
}
if ($data = $form->get_data()) {
    if (!$material) {
        $material = (object) [
            'livecourseid' => $livecourse->id,
            'title' => $data->title,
            'materialtype' => 'page',
            'url' => '',
            'description' => '',
            'descriptionformat' => FORMAT_HTML,
            'content' => '',
            'contentformat' => FORMAT_HTML,
            'displaytitle' => (int) $data->displaytitle,
            'displaydescription' => (int) $data->displaydescription,
            'visible' => 1,
            'sortorder' => $DB->count_records('livecourse_material', ['livecourseid' => $livecourse->id]) + 1,
            'timecreated' => time(),
        ];
        $material->id = $DB->insert_record('livecourse_material', $material);
    }
    $data->id = $material->id;
    $data = file_postupdate_standard_editor($data, 'content', $editoroptions, $context,
        'mod_livecourse', 'content', $material->id);
    $material->title = $data->title;
    $material->description = $data->description_editor['text'];
    $material->descriptionformat = $data->description_editor['format'];
    $material->content = $data->content;
    $material->contentformat = $data->contentformat;
    $material->displaytitle = (int) $data->displaytitle;
    $material->displaydescription = (int) $data->displaydescription;
    $DB->update_record('livecourse_material', $material);
    livecourse_publish_event($cm->id);
    redirect($returnurl, get_string('pagesaved', 'mod_livecourse'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$defaults = $material ?: (object) [
    'id' => null,
    'title' => '',
    'description' => '',
    'descriptionformat' => FORMAT_HTML,
    'content' => '',
    'contentformat' => FORMAT_HTML,
    'displaytitle' => 1,
    'displaydescription' => 1,
];
$defaults = file_prepare_standard_editor($defaults, 'content', $editoroptions, $context,
    'mod_livecourse', 'content', $material?->id);
$defaults->description_editor = [
    'text' => $defaults->description ?? '',
    'format' => $defaults->descriptionformat ?? FORMAT_HTML,
];
$form->set_data($defaults);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($material ? 'editcontentpage' : 'addcontentpage', 'mod_livecourse'));
$form->display();
echo $OUTPUT->footer();
