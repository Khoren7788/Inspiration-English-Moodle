<?php
defined('MOODLE_INTERNAL') || die();

function livecourse_supports(string $feature): ?bool {
    return match ($feature) {
        FEATURE_MOD_ARCHETYPE => MOD_ARCHETYPE_RESOURCE,
        FEATURE_MOD_INTRO => true,
        FEATURE_COMPLETION_TRACKS_VIEWS => true,
        FEATURE_SHOW_DESCRIPTION => true,
        FEATURE_MOD_PURPOSE => MOD_PURPOSE_CONTENT,
        FEATURE_GRADE_HAS_GRADE => false,
        FEATURE_GRADE_OUTCOMES => false,
        FEATURE_GROUPS => false,
        FEATURE_GROUPINGS => false,
        FEATURE_BACKUP_MOODLE2 => false,
        default => null,
    };
}

function livecourse_get_file_areas($course, $cm, $context): array {
    return ['content' => get_string('content', 'page')];
}

function livecourse_add_instance(stdClass $data, mod_livecourse_mod_form $mform = null): int {
    global $DB;
    $cmid = $data->coursemodule;
    $content = $mform ? $data->page['text'] : '';
    $contentformat = $mform ? $data->page['format'] : FORMAT_HTML;
    $draftitemid = $mform ? $data->page['itemid'] : 0;
    $displaydescription = !empty($data->printintro);
    $displaylastmodified = !empty($data->printlastmodified);
    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    unset($data->page, $data->printintro, $data->printlastmodified, $data->initialmaterialid);
    $data->id = $DB->insert_record('livecourse', $data);
    $DB->set_field('course_modules', 'instance', $data->id, ['id' => $cmid]);
    $material = (object) [
        'livecourseid' => $data->id,
        'title' => $data->name,
        'materialtype' => 'page',
        'url' => '',
        'description' => $data->intro ?? '',
        'descriptionformat' => $data->introformat ?? FORMAT_HTML,
        'content' => $content,
        'contentformat' => $contentformat,
        'displaytitle' => 1,
        'displaydescription' => $displaydescription,
        'displaylastmodified' => $displaylastmodified,
        'visible' => 1,
        'sortorder' => 1,
        'timecreated' => time(),
        'timemodified' => time(),
    ];
    $material->id = $DB->insert_record('livecourse_material', $material);
    if ($draftitemid) {
        $context = context_module::instance($cmid);
        $material->content = file_save_draft_area_files($draftitemid, $context->id, 'mod_livecourse',
            'content', $material->id, livecourse_editor_options($context, $data->course), $material->content);
        $DB->update_record('livecourse_material', $material);
    }
    return $data->id;
}

function livecourse_update_instance(stdClass $data, mod_livecourse_mod_form $mform = null): bool {
    global $DB;
    $cmid = $data->coursemodule;
    $materialid = (int) ($data->initialmaterialid ?? 0);
    $content = $mform ? $data->page['text'] : '';
    $contentformat = $mform ? $data->page['format'] : FORMAT_HTML;
    $draftitemid = $mform ? $data->page['itemid'] : 0;
    $displaydescription = !empty($data->printintro);
    $displaylastmodified = !empty($data->printlastmodified);
    $data->id = $data->instance;
    $data->timemodified = time();
    unset($data->page, $data->printintro, $data->printlastmodified, $data->initialmaterialid);
    $result = $DB->update_record('livecourse', $data);
    $material = $materialid ? $DB->get_record('livecourse_material', [
        'id' => $materialid, 'livecourseid' => $data->id, 'materialtype' => 'page',
    ]) : false;
    if (!$material) {
        $material = (object) [
            'livecourseid' => $data->id,
            'materialtype' => 'page',
            'url' => '',
            'visible' => 1,
            'sortorder' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $material->id = $DB->insert_record('livecourse_material', $material);
    }
    $material->title = $data->name;
    $material->description = $data->intro ?? '';
    $material->descriptionformat = $data->introformat ?? FORMAT_HTML;
    $material->content = $content;
    $material->contentformat = $contentformat;
    $material->displaytitle = 1;
    $material->displaydescription = $displaydescription;
    $material->displaylastmodified = $displaylastmodified;
    $material->timemodified = time();
    if ($draftitemid) {
        $context = context_module::instance($cmid);
        $material->content = file_save_draft_area_files($draftitemid, $context->id, 'mod_livecourse',
            'content', $material->id, livecourse_editor_options($context, $data->course), $material->content);
    }
    $DB->update_record('livecourse_material', $material);
    return $result;
}

function livecourse_editor_options(context $context, int $courseid): array {
    global $DB;
    $course = $DB->get_record('course', ['id' => $courseid], 'maxbytes', MUST_EXIST);
    return [
        'maxfiles' => -1,
        'maxbytes' => $course->maxbytes,
        'trusttext' => true,
        'context' => $context,
        'subdirs' => true,
    ];
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
    $materials = $DB->get_records('livecourse_material', ['livecourseid' => $id], '', 'id');
    $fs = get_file_storage();
    $cm = get_coursemodule_from_instance('livecourse', $id);
    if ($cm) {
        $contextid = context_module::instance($cm->id)->id;
        foreach ($materials as $material) {
            $fs->delete_area_files($contextid, 'mod_livecourse', 'content', $material->id);
        }
    }
    $DB->delete_records('livecourse_material', ['livecourseid' => $id]);
    $DB->delete_records('livecourse', ['id' => $id]);
    return true;
}

function livecourse_pluginfile($course, $cm, $context, string $filearea, array $args,
        bool $forcedownload, array $options = []): bool {
    global $DB;
    if ($context->contextlevel !== CONTEXT_MODULE || $filearea !== 'content') {
        return false;
    }
    require_login($course, true, $cm);
    require_capability('mod/livecourse:view', $context);
    $itemid = (int) array_shift($args);
    $material = $DB->get_record('livecourse_material', ['id' => $itemid, 'livecourseid' => $cm->instance],
        'id,visible');
    if (!$material || (!$material->visible && !has_capability('mod/livecourse:manage', $context))) {
        return false;
    }
    $filename = array_pop($args);
    $filepath = '/' . ($args ? implode('/', $args) . '/' : '');
    $file = get_file_storage()->get_file($context->id, 'mod_livecourse', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
    return true;
}

function livecourse_get_youtube_embed_url(string $url): ?string {
    $parts = parse_url($url);
    if (!$parts || empty($parts['host'])) {
        return null;
    }
    $host = strtolower($parts['host']);
    $videoid = null;
    if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
        parse_str($parts['query'] ?? '', $query);
        $videoid = $query['v'] ?? null;
        if (!$videoid && str_starts_with($parts['path'] ?? '', '/shorts/')) {
            $videoid = explode('/', trim($parts['path'], '/'))[1] ?? null;
        }
    } else if ($host === 'youtu.be') {
        $videoid = trim($parts['path'] ?? '', '/');
    }
    if (!$videoid || !preg_match('/^[a-zA-Z0-9_-]{6,20}$/', $videoid)) {
        return null;
    }
    return 'https://www.youtube-nocookie.com/embed/' . $videoid;
}

function livecourse_base64url_encode(string $value): string {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function livecourse_create_websocket_token(int $userid, int $cmid): string {
    $secret = (string) getenv('LIVECOURSE_WS_SECRET');
    if (strlen($secret) < 32) {
        throw new moodle_exception('websocketnotconfigured', 'mod_livecourse');
    }
    $payload = livecourse_base64url_encode(json_encode([
        'uid' => $userid,
        'cmid' => $cmid,
        'exp' => time() + 14400,
    ], JSON_THROW_ON_ERROR));
    $signature = livecourse_base64url_encode(hash_hmac('sha256', $payload, $secret, true));
    return $payload . '.' . $signature;
}

function livecourse_publish_event(int $cmid): void {
    try {
        $redis = new Redis();
        $redis->connect((string) (getenv('VALKEY_HOST') ?: 'valkey'), (int) (getenv('VALKEY_PORT') ?: 6379), 1.5);
        $password = (string) getenv('VALKEY_PASSWORD');
        if ($password !== '') {
            $redis->auth($password);
        }
        $redis->publish('livecourse:' . $cmid, 'refresh');
        $redis->close();
    } catch (Throwable $exception) {
        debugging('Live course WebSocket publish failed: ' . $exception->getMessage(), DEBUG_DEVELOPER);
    }
}
