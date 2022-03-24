<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_completionreport;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Completion user form class.
 *
 * @package   local_completionreport
 * @author    Jomari Tayag
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class userform extends \moodleform {
    /**
     * Form definition
     */
    public function definition() {
        $mform = $this->_form;

        $users = $this->get_users();
        $mform->addElement("select", "userid", get_string("form:users", "local_completionreport"), $users);
        $mform->setDefault("users", 0);

        $this->add_action_buttons(false, get_string("form:search", "local_completionreport"));
    }

    /**
     * Helper function to get users.
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     */
    private function get_users() {
        global $DB;
        $finalusers = [];
        $allusers = $DB->get_records("user");

        $finalusers[] = get_string("form:any_user", "local_completionreport");
        foreach ($allusers as $user) {
            $finalusers[$user->id] = fullname($user);
        }

        return $finalusers;
    }
}
