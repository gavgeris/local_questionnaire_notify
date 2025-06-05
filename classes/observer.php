<?php
namespace local_questionnaire_notify;

defined('MOODLE_INTERNAL') || die();

use mod_questionnaire\event\response_submitted;
use core_user;

class observer {
    public static function on_response_submitted(response_submitted $event): void {
        global $DB;

        $userid = $event->userid;
        $cmid = $event->contextinstanceid;

        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
        $cm = get_coursemodule_from_id('questionnaire', $cmid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

        $subject = get_string('emailsubject', 'local_questionnaire_notify', $course->fullname);
        $body = get_string('emailbody', 'local_questionnaire_notify', [
            'firstname' => $user->firstname,
            'coursename' => $course->fullname
        ]);

        email_to_user($user, core_user::get_support_user(), $subject, $body);
    }
}
