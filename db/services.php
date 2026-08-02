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
 * External functions and services for block_activity_copy_cart.
 *
 * @package    block_activity_copy_cart
 * @author     ZikaZaki <zika.github@gmail.com>
 * @copyright  2026 Numo <https://numo.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


$functions = [
    'block_activity_copy_cart_save_cart' => [
        'classname' => 'block_activity_copy_cart\external\save_cart',
        'description' => 'Autosaves the drag-and-drop cart\'s current contents so they survive a refresh.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'block/activity_copy_cart:copyactivities',
    ],
    'block_activity_copy_cart_get_target_tree_node' => [
        'classname' => 'block_activity_copy_cart\external\get_tree_node',
        'description' => 'Fetches the subcategories and courses directly under one category, for the target course tree.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'block/activity_copy_cart:copyactivities',
    ],
    'block_activity_copy_cart_save_target_courses' => [
        'classname' => 'block_activity_copy_cart\external\save_target_courses',
        'description' => 'Autosaves the tree\'s current course/category picks so they survive a refresh or back-navigation.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'block/activity_copy_cart:copyactivities',
    ],
    'block_activity_copy_cart_get_job_progress' => [
        'classname' => 'block_activity_copy_cart\external\get_job_progress',
        'description' => 'Polled by the copy progress page to report a job\'s status and per-item results.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'block/activity_copy_cart:copyactivities',
    ],
];
