<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package     block_activity_copy_cart
 * @category    string
 * @author      ZikaZaki <zika.github@gmail.com>
 * @copyright   2026 Numo <https://numo.sa>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Activity Copy Cart';
$string['activity_copy_cart:addinstance'] = 'Add a new Activity Copy Cart block';
$string['activity_copy_cart:myaddinstance'] = 'Add a new Activity Copy Cart block to Dashboard';
$string['activity_copy_cart:copyactivities'] = 'Copy activities to other courses';
$string['nopermissions'] = 'You do not have permission to copy activities out of this course.';
$string['clearcart'] = 'Clear all';
$string['cartempty'] = 'Your cart is empty. Drag and drop activities here.';
$string['copyactivities'] = 'Copy activities';
$string['previewcopy'] = 'Preview';
$string['addtocopycart'] = 'Add to copy cart';

// Target course selection page.
$string['selectcoursestitle'] = 'Select target courses';
$string['selectcourses'] = 'Courses to copy into';
$string['cartsummary'] = 'Activities to copy ({$a})';
$string['selectedcourses'] = 'Target courses ({$a})';
$string['cartexpired'] = 'Your cart has expired or is empty. Please add activities to it again.';
$string['cartinvalid'] = 'The submitted cart is invalid.';
$string['notargetschosen'] = 'Choose at least one target course.';
$string['badgerenamed'] = 'Renamed to: {$a}';
$string['backtoselection'] = 'Back to course selection';

// Copy execution.
$string['copyprogresstitle'] = 'Copying activities';
$string['copyprogressheading'] = '{$a->completedunits} of {$a->totalunits} copies completed';
$string['jobnotfound'] = 'This copy job could not be found, or has expired.';
$string['backtocourse'] = 'Back to course';
$string['logtargetcourse'] = 'Target course';
$string['logactivity'] = 'Activity';
$string['logstatus'] = 'Status';
$string['logmessage'] = 'Details';
$string['statuspending'] = 'Queued';
$string['statusrunning'] = 'In progress';
$string['statuscompleted'] = 'Completed';
$string['statuscompletedwitherrors'] = 'Completed with issues';
$string['statusfailed'] = 'Failed';
$string['resultsuccess'] = 'Copied';
$string['resultskipped'] = 'Skipped';
$string['resultfailed'] = 'Failed';
$string['skipsectionmissing'] = 'Target section "{$a}" does not exist in this course.';
$string['skipnameconflict'] = 'An activity named "{$a}" already exists in the target section.';
$string['errorbackupfailed'] = 'Backing up this activity failed, so it could not be copied into any target course.';
$string['errorrestoreprecheck'] = 'Restore precheck failed: {$a}';
$string['errorrestorefailed'] = 'The restore finished but did not produce a new activity in the target course.';
$string['errorsourcecapabilitylost'] = 'You no longer have permission to copy activities out of the source course - the job was stopped.';
$string['errortargetcapabilitylost'] = 'You no longer have permission to copy into this course.';
$string['copycompletedmessagesubject'] = 'Your activity copy has finished';
$string['copycompletedmessagebody'] = '{$a->completedunits} of {$a->totalunits} copies finished with status: {$a->status}.';
$string['messageprovider:copycompleted'] = 'Notification that a queued activity copy has finished';

// Privacy.
$string['privacy:metadata:job'] = 'A record of one "Copy Activities" job - which activities, into which courses, and how far it got.';
$string['privacy:metadata:job:userid'] = 'The id of the user who started the copy job.';
$string['privacy:metadata:job:sourcecourseid'] = 'The course the copied activities came from.';
$string['privacy:metadata:job:cart'] = 'A snapshot of the activities and their per-activity settings at the time the job was started.';
$string['privacy:metadata:job:targetcourseids'] = 'The courses the activities were copied into.';
$string['privacy:metadata:job:status'] = 'The job\'s progress status.';
$string['privacy:metadata:job:timecreated'] = 'The time the job was started.';
$string['privacy:metadata:jobbackup'] = 'A record of one cart item\'s single backup within a copy job, reused across every target course that item is copied into.';
$string['privacy:metadata:jobbackup:jobid'] = 'The copy job this backup belongs to.';
$string['privacy:metadata:jobbackup:sourcecmid'] = 'The activity that was backed up.';
$string['privacy:metadata:jobbackup:status'] = 'The backup\'s own status.';

// Target course tree.
$string['searchcourses'] = 'Search for a course';
$string['nosearchresults'] = 'No matching courses found.';
$string['nocategoriesavailable'] = 'No categories are available.';
$string['nocoursesavailable'] = 'No courses available in this category.';
$string['expandcategory'] = 'Expand category';
$string['noscript'] = 'This feature requires JavaScript to be enabled in your browser.';

// Item settings modal.
$string['settings'] = 'Activity Copy Settings';

// Activity Details group.
$string['settingsgeneral'] = 'General';
$string['renameactivity'] = 'Activity name';
$string['rename_info'] = 'The name the copied activity will have in each target course.';
$string['nameconflict'] = 'Activity name conflicts';
$string['nameconflict_info'] = 'What to do if an activity with this name already exists in the target section.';
$string['resolveconflict'] = 'Auto-rename';
$string['skipactivity'] = 'Skip copy';
$string['targetvisibility'] = 'Activity visibility';
$string['visibility_info'] = 'Whether the copied activity is shown or hidden to students in the target course.';
$string['visibilitysource'] = 'Same as source activity';
$string['visibilityshow'] = 'Show';
$string['visibilityhide'] = 'Hide';
$string['settingsadvanced'] = 'Advanced';
$string['groupcontentdata'] = 'Activity content & data';
$string['contentdata_info'] = 'Access restrictions may reference dates, groups or grades that do not exist in the target course.';
$string['keeprestrictions'] = 'Keep access restrictions (dates, groups, grade conditions, etc.)';

// Target Section Details group.
$string['settingsplacement'] = 'Placement';
$string['targetsection'] = 'Section name';
$string['section_info'] = 'The section this activity currently belongs to. Used as the reference point for locating the equivalent section in each target course.';
$string['matchsectionby'] = 'Match section by';
$string['sectionmatch_info'] = 'Position matching is more reliable than name matching when target courses use different section names or dates.';
$string['matchbyname'] = 'Name';
$string['matchbyposition'] = 'Position (number)';
$string['sectionmissing'] = 'Missing target section';
$string['sectionmissing_info'] = 'What to do if the target course does not have this section yet.';
$string['createnewsection'] = 'Auto-create';

// Item settings modal validation.
$string['error_sectionrequired'] = 'Target section is required.';
$string['error_sectionmatchrequired'] = 'Select how to match the section.';
$string['error_sectionmissingrequired'] = 'Choose what to do when the target section is missing.';
$string['error_nameconflictrequired'] = 'Choose how to handle a name conflict.';
$string['error_visibilityrequired'] = 'Select a visibility option.';
$string['error_summaryheading'] = 'Please fix the following:';
