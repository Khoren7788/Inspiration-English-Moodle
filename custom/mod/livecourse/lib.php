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
    $DB->delete_records('livecourse_material', ['livecourseid' => $id]);
    $DB->delete_records('livecourse', ['id' => $id]);
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
