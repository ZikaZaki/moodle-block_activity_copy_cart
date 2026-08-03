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
 * Block activity_copy_cart is defined here.
 *
 * @package     block_activity_copy_cart
 * @author      ZikaZaki <zika.github@gmail.com>
 * @copyright   2026 Numo <https://numo.sa>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_activity_copy_cart\output\cart_block;

/**
 * The Activity Copy Cart course block.
 */
class block_activity_copy_cart extends block_base {
    /**
     * Initializes class member variables.
     */
    public function init() {
        // Needed by Moodle to differentiate between blocks.
        $this->title = get_string('pluginname', 'block_activity_copy_cart');
    }

    /**
     * Builds the block contents.
     *
     * @return stdClass The block contents.
     */
    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        if (!$this->page->user_is_editing()) {
            return $this->content;
        }

        $coursecontext = context_course::instance($this->page->course->id);
        $canbackup = has_capability('block/activity_copy_cart:copyactivities', $coursecontext);
        if (!$canbackup) {
            $this->content->text = get_string('nopermissions', 'block_activity_copy_cart');
            return $this->content;
        }

        global $OUTPUT;

        $this->content->text = $OUTPUT->render(new cart_block((int) $this->page->course->id));

        $this->page->requires->js_call_amd('block_activity_copy_cart/local/block', 'init', [
            'activitycopycart-root',
            $canbackup,
            $this->page->user_is_editing(),
        ]);

        return $this->content;
    }

    /**
     * Defines configuration data.
     *
     * The function is called immediately after init().
     */
    public function specialization() {

        // Load user defined title and make sure it's never empty.
        if (empty($this->config->title)) {
            $this->title = get_string('pluginname', 'block_activity_copy_cart');
        } else {
            $this->title = $this->config->title;
        }
    }

    /**
     * Enables global configuration of the block in settings.php.
     *
     * @return bool True if the global configuration is enabled.
     */
    public function has_config() {
        // settings.php has no actual settings yet - flip this back to true once it does,
        // rather than shipping a "Settings" link that leads to an empty page.
        return false;
    }

    /**
     * Restricts this block to course view pages.
     *
     * @return string[] Array of pages and permissions.
     */
    public function applicable_formats() {
        return ['course-view' => true];
    }
}
