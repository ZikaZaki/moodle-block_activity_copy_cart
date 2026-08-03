<?php

require(__DIR__ . '/../../config.php');

use block_activity_copy_cart\app\block\manager as cart_manager;
use block_activity_copy_cart\app\block\repository as cart_repository;
use block_activity_copy_cart\app\copy\manager as copy_manager;
use block_activity_copy_cart\app\target\courses_tree;
use block_activity_copy_cart\app\target\repository as target_repository;
use block_activity_copy_cart\app\wizard;
use block_activity_copy_cart\form\target_courses_form;
use block_activity_copy_cart\output\target_courses_page;


require_login();

if (data_submitted() && optional_param_array('cmids', null, PARAM_INT) !== null) {
    require_sesskey();
    try {
        // Resolving the source course is cheap (one query) - authorizing against it before
        // paying for from_submitted_data()'s more expensive per-item hydration closes the gap
        // where an unauthorized caller could otherwise make this endpoint do that work anyway.
        $cmids = optional_param_array('cmids', [], PARAM_INT);
        require_capability(
            'block/activity_copy_cart:copyactivities',
            context_course::instance(cart_manager::resolve_source_course($cmids))
        );
        cart_repository::save(cart_manager::from_submitted_data());
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
    wizard::cancel($courseurl);
}

if (optional_param('confirmcopy', false, PARAM_BOOL)) {
    require_sesskey();

    try {
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
    } catch (moodle_exception $e) {
        redirect($pageurl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }

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
    wizard::cancel($courseurl);
}

$reviewing = false;
$targetcourserows = [];

if ($data = $mform->get_data()) {
    $selected = target_courses_form::selected_ids($data);
    target_repository::save($course->id, $selected['courseids'], $selected['categoryids']);

    $expansionerror = null;
    try {
        $candidateids = array_merge($selected['courseids'], courses_tree::expand_categories($selected['categoryids']));
        $validids = courses_tree::filter($candidateids, $course->id);
    } catch (moodle_exception $e) {
        $validids = [];
        $expansionerror = $e->getMessage();
    }

    if ($expansionerror !== null) {
        \core\notification::error($expansionerror);
    } else if (empty($validids)) {
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
