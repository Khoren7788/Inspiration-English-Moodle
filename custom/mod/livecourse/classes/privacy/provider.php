<?php
namespace mod_livecourse\privacy;

use core_privacy\local\metadata\collection;

class provider implements \core_privacy\local\metadata\provider {
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('livecourse_response', [
            'sessionid' => 'privacy:metadata:response:sessionid',
            'questionid' => 'privacy:metadata:response:questionid',
            'userid' => 'privacy:metadata:response:userid',
            'answer' => 'privacy:metadata:response:answer',
            'iscorrect' => 'privacy:metadata:response:iscorrect',
            'timeanswered' => 'privacy:metadata:response:timeanswered',
        ], 'privacy:metadata:response');
        return $collection;
    }
}

