<?php
namespace local_questionnaire_notify;

defined('MOODLE_INTERNAL') || die();

use mod_questionnaire\event\response_submitted;
use core_user;

class observer {
    public static function on_response_submitted(response_submitted $event): void {
        global $DB;

        debugging('Observer triggered: on_response_submitted()', DEBUG_DEVELOPER);

        $userid = $event->userid;
        $cmid = $event->contextinstanceid;

        error_log("[questionnaire_notify] Event received. User ID: $userid, CM ID: $cmid");

        try {
            $cm = get_coursemodule_from_id('questionnaire', $cmid, 0, false, MUST_EXIST);
            $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
            $questionnaire = $DB->get_record('questionnaire', ['id' => $cm->instance], '*', MUST_EXIST);

            error_log("[questionnaire_notify] Questionnaire ID: {$questionnaire->id}, Course ID: {$course->id}");

            $handler = \core_customfield\handler::get_handler('mod_questionnaire', 'mod_questionnaire');
            $data = $handler->get_instance_data($questionnaire->id);

            $sendemail = false;
            foreach ($data as $fielddata) {
                $shortname = $fielddata->get_field()->get('shortname');
                $value = $fielddata->get_value();
                error_log("[questionnaire_notify] Found custom field: $shortname = $value");

                if ($shortname === 'emailonresponse' && $value === '1') {
                    $sendemail = true;
                    error_log("[questionnaire_notify] Email sending enabled for this questionnaire.");
                    break;
                }
            }

            if (!$sendemail) {
                error_log("[questionnaire_notify] Email sending is disabled. Exiting.");
                return;
            }

            $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);

            $subject = get_string('emailsubject', 'local_questionnaire_notify', $course->fullname);
            $body = get_string('emailbody', 'local_questionnaire_notify', [
                'firstname' => $user->firstname,
                'coursename' => $course->fullname
            ]);

            if (email_to_user($user, core_user::get_support_user(), $subject, $body)) {
                error_log("[questionnaire_notify] Email sent successfully to {$user->email}");
            } else {
                error_log("[questionnaire_notify] Failed to send email to {$user->email}");
            }

        } catch (\Throwable $e) {
            error_log('[questionnaire_notify] Exception occurred: ' . $e->getMessage());
            debugging('Exception in observer: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

}
