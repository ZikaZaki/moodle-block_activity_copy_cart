<?php

namespace block_activity_copy_cart\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use moodleform;
use block_activity_copy_cart\app\target\courses_tree;


class target_courses_form extends moodleform {
    const COURSEIDS_FIELD_ID = 'activitycopycart-target-courseids';
    const CATEGORYIDS_FIELD_ID = 'activitycopycart-target-categoryids';

    protected function definition() {
        global $OUTPUT, $PAGE;

        $mform = $this->_form;
        $sourcecourseid = (int) $this->_customdata['sourcecourseid'];
        $savedcourseids = $this->_customdata['savedcourseids'] ?? [];
        $savedcategoryids = $this->_customdata['savedcategoryids'] ?? [];

        $mform->addElement('hidden', 'targetcourseids', '', ['id' => self::COURSEIDS_FIELD_ID]);
        $mform->setType('targetcourseids', PARAM_SEQUENCE);
        $mform->setDefault('targetcourseids', implode(',', $savedcourseids));
        $mform->addElement('hidden', 'targetcategoryids', '', ['id' => self::CATEGORYIDS_FIELD_ID]);
        $mform->setType('targetcategoryids', PARAM_SEQUENCE);
        $mform->setDefault('targetcategoryids', implode(',', $savedcategoryids));

        $treehtml = $OUTPUT->render_from_template('block_activity_copy_cart/target/tree', [
            'sourcecourseid' => $sourcecourseid,
            'rootcategories' => courses_tree::children(0, $sourcecourseid)['categories'],
        ]);

        $card = \html_writer::div(get_string('selectcourses', 'block_activity_copy_cart'), 'card-header')
            . \html_writer::div($treehtml, 'card-body');
        $mform->addElement('html', \html_writer::div($card, 'card mb-3'));

        $mform->addElement('static', 'targettreeerror', '', '');

        $mform->addElement(
            'html',
            \html_writer::tag('noscript', $OUTPUT->notification(get_string('noscript', 'block_activity_copy_cart'), 'warning'))
        );

        $this->add_action_buttons(true, get_string('previewcopy', 'block_activity_copy_cart'));

        $PAGE->requires->js_call_amd('block_activity_copy_cart/app/target/factory', 'init', [
            self::COURSEIDS_FIELD_ID,
            self::CATEGORYIDS_FIELD_ID,
            $sourcecourseid,
            courses_tree::restore_paths($savedcourseids, $savedcategoryids),
        ]);
    }

    public static function selected_ids(\stdClass $data): array {
        return [
            'courseids' => self::parse_sequence($data->targetcourseids ?? ''),
            'categoryids' => self::parse_sequence($data->targetcategoryids ?? ''),
        ];
    }

    private static function parse_sequence(string $sequence): array {
        if ($sequence === '') {
            return [];
        }
        return array_map('intval', explode(',', $sequence));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $hascourses = !empty($data['targetcourseids']);
        $hascategories = !empty($data['targetcategoryids']);
        if (!$hascourses && !$hascategories) {
            $errors['targettreeerror'] = get_string('notargetschosen', 'block_activity_copy_cart');
        }

        return $errors;
    }
}
