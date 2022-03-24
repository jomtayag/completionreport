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

/**
 * Completion report
 *
 * @package    local_completionreport
 * @author     Jomari Tayag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_completionreport\userform as UserForm;

require_once(__DIR__.'/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Parameters.
$defaultsort = "c.fullname";
$userid = optional_param('userid', '', PARAM_INT); // User ID.
$sort = optional_param("sort", $defaultsort, PARAM_NOTAGS); // Sorting column.
$dir = optional_param("dir", "ASC", PARAM_ALPHA);    // Sorting direction.
$page = optional_param("page", 0, PARAM_INT);        // Page number.
$perpage = optional_param("perpage", 30, PARAM_INT); // Results to display per page.

$pageparams = array();
if ($userid) {
    $pageparams['userid'] = $userid;
}
if ($sort) {
    $pageparams['sort'] = $sort;
}
if ($dir) {
    $pageparams['dir'] = $dir;
}
$pageparams['page'] = $page;
$pageparams['perpage'] = $perpage;

admin_externalpage_setup('local_completionreport', '', $pageparams);

// Check if user is not admin.
if (!is_siteadmin()) {
    throw new moodle_exception('No Permission');
}

$mform = new UserForm(new moodle_url('/local/completionreport/'));

$data = $mform->get_data();
if (!$data && $pageparams['userid']) {
    $mform->set_data((object)$pageparams);
}

echo $OUTPUT->header();
$mform->display();

// Dir can only be DESC or ASC.
$dir = $dir === 'DESC' ? 'DESC' : 'ASC';

// Ensure the maxiumum records perpage is not ever set too high.
$perpage = min(100, $perpage);

// Columns of the report.
$columns = array(
    "c.fullname",
    "completionstatus",
    "timecompleted",
);

// Sort sql fields for each column.
$scolumns = array(
    'c.fullname' => array('c.fullname'),
    'completionstatus' => array('completionstatus'),
    'timecompleted' => array('timecompleted'),
);

// Build array of all the possible sort columns.
$allsorts = array();
foreach ($scolumns as $sorts) {
    foreach ($sorts as $s) {
        $allsorts[] = $s;
    }
}

// Sanitize sort to ensure they are valid column sorts.
if (!in_array($sort, $allsorts)) {
    $sort = $defaultsort;
}

$where = "";
if ($data || $pageparams['userid']) {
    $where = "WHERE cc.userid = :userid";
}

$orderby = "ORDER BY $sort $dir";

// Show table after a user has been selected and has enrolled courses, otherwise, DO NOT SHOW IT.
if ($data->userid || $pageparams['userid']) {

    // Url used for sorting the table.
    $baseurl = new moodle_url('/local/completionreport/', $pageparams);

    // Tried using this but does not get status and time completion.
    // $courses = enrol_get_all_users_courses();.

    // NOTE: Transfer this to a class.
    $sql = "SELECT cc.id, cc.userid, cc.course, c.fullname, cc.timestarted, cc.timecompleted,
               uet.timecreated as enrolmentcreated
        FROM {course_completions} AS cc
        JOIN {user} AS u ON cc.userid = u.id
        JOIN {course} AS c ON cc.course = c.id
        JOIN (SELECT max(ue.timecreated) as timecreated, ue.userid, e.courseid
                FROM {user_enrolments} ue
                JOIN {enrol} e ON (e.id = ue.enrolid)
                GROUP by ue.userid, e.courseid) uet ON uet.courseid = c.id AND uet.userid = u.id
        $where $orderby";

    $currentstart = $page * $perpage; // Count of where to start with records.

    $records = $DB->get_records_sql($sql, $pageparams, $currentstart, $perpage);

    if (empty($records)) {
        echo get_string("noenrolledcourse", 'local_completionreport');
        echo $OUTPUT->footer();
        exit;
    }

    echo $OUTPUT->paging_bar($changescount, $page, $perpage, $baseurl);

    // Calculate table headers (clickable links that do sorting).
    $hcolumns = array();
    // Foreach column we look at it's applicable sort columns and build a final link header.
    foreach ($columns as $column) {
        $final = array();
        foreach ($scolumns[$column] as $sortcolumn) {
            if ($sort != $sortcolumn) {
                $cdir = $dir;
                $cicon = "";
            } else {
                $cdir = $dir == "ASC" ? "DESC" : "ASC";
                $cicondir = ($dir == "ASC") ? "down" : "up";
                $cicon = $OUTPUT->pix_icon('t/'. $cicondir, get_string($cicondir));
            }
            // Get a string for this sort link.
            $columnheader = get_string("table:sort_header_$sortcolumn", 'local_completionreport');
            // Update parameters for sort and direction for this column in the final url.
            $baseurl->param('sort', $sortcolumn);
            $baseurl->param('dir', $cdir);

            if ($sortcolumn === "completionstatus") {
                $final[] = "<a href=javascript:void(0)>$columnheader</a>";
            } else {
                $final[] = "<a href=$baseurl#table>$columnheader</a>$cicon";
            }
        }
        // If one column has multiple sorts, combine them into one entry for that column.
        $hcolumns[$column] = implode('/', $final);
    }

    $table = new html_table();
    $table->head = $hcolumns;
    $table->data = [];

    foreach ($records as $course) {

        $final = new stdClass();
        $final->fullname = html_writer::link(new moodle_url('/course/view.php',
            array('id' => $course->fullname)), $course->fullname);
        $final->completionstatus = $course->timecompleted ? "COMPLETE" : "NOT COMPLETE";
        $final->timecompleted = !empty($course->timecompleted) ? userdate($course->timecompleted,
            get_string('strftimedatetime', 'langconfig')) : "-";

        $table->data[] = $final;
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
