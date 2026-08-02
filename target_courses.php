<?php

require(__DIR__ . '/../../config.php');

use block_activity_copy_cart\app\block\manager as cart_manager;
use block_activity_copy_cart\app\block\repository as cart_repository;
use block_activity_copy_cart\app\copy\manager as copy_manager;
use block_activity_copy_cart\app\target\courses_tree;
use block_activity_copy_cart\app\target\repository as target_repository;
use block_activity_copy_cart\form\target_courses_form;
use block_activity_copy_cart\output\target_courses_page;


function cancel_wizard(moodle_url $courseurl): void {
    cart_repository::clear();
    target_repository::clear();
    redirect($courseurl);
}

require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && optional_param_array('cmids', null, PARAM_INT) !== null) {
    require_sesskey();
    try {
        $submittedcart = cart_manager::from_submitted_data();
        require_capability(
            'block/activity_copy_cart:copyactivities',
            context_course::instance($submittedcart['sourcecourseid'])
        );
        cart_repository::save($submittedcart);
    } catch (moodle_exception $e) {
        redirect(new moodle_url('/my/'), $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
    redirect(new moodle_url('/blocks/activity_copy_cart/target_courses.php'));
}

$cart = cart_repository::get();
if (!$cart) {
    redirect(
        new moodle_url('/my/'),
        get_string('cartexpired', 'block_activity_copy_cart'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$course = get_course($cart['sourcecourseid']);
$context = context_course::instance($course->id);

require_login($course);
require_capability('block/activity_copy_cart:copyactivities', $context);

$pageurl = new moodle_url('/blocks/activity_copy_cart/target_courses.php');
$courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);

if (optional_param('cancelcopy', false, PARAM_BOOL)) {
    require_sesskey();
    cancel_wizard($courseurl);
}

if (optional_param('confirmcopy', false, PARAM_BOOL)) {
    require_sesskey();

    $saved = target_repository::get($course->id);
    $candidateids = array_merge($saved['courseids'], courses_tree::expand_categories($saved['categoryids']));
    $validids = courses_tree::filter($candidateids, $course->id);

    if (empty($validids)) {
        redirect(
            $pageurl,
            get_string('notargetschosen', 'block_activity_copy_cart'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    $jobid = copy_manager::create_job($cart, $validids);
    cart_repository::clear();
    target_repository::clear();
    redirect(new moodle_url('/blocks/activity_copy_cart/copy_progress.php', ['jobid' => $jobid]));
}

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('selectcoursestitle', 'block_activity_copy_cart'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('pluginname', 'block_activity_copy_cart'), $courseurl);
$PAGE->navbar->add(get_string('selectcoursestitle', 'block_activity_copy_cart'));

$saved = target_repository::get($course->id);
$mform = new target_courses_form($pageurl, [
    'sourcecourseid' => $course->id,
    'savedcourseids' => $saved['courseids'],
    'savedcategoryids' => $saved['categoryids'],
]);

if ($mform->is_cancelled()) {
    cancel_wizard($courseurl);
}

$reviewing = false;
$targetcourserows = [];

if ($data = $mform->get_data()) {
    $selected = target_courses_form::selected_ids($data);
    target_repository::save($course->id, $selected['courseids'], $selected['categoryids']);

    $candidateids = array_merge($selected['courseids'], courses_tree::expand_categories($selected['categoryids']));
    $validids = courses_tree::filter($candidateids, $course->id);
    if (empty($validids)) {
        \core\notification::error(get_string('notargetschosen', 'block_activity_copy_cart'));
    } else {
        $reviewing = true;
        $targetcourserows = copy_manager::target_courses($validids);
    }
}

$renderable = new target_courses_page(
    $courseurl,
    $pageurl,
    $reviewing,
    $cart,
    $targetcourserows,
    $reviewing ? '' : $mform->render()
);

echo $OUTPUT->header();
echo $OUTPUT->render($renderable);
echo $OUTPUT->footer();
