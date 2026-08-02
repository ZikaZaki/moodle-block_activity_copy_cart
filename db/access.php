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
 * Capability definitions for block_activity_copy_cart.
 *
 * @package    block_activity_copy_cart
 * @author     ZikaZaki <zika.github@gmail.com>
 * @copyright  2026 Numo <https://numo.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


$capabilities = [
    'block/activity_copy_cart:addinstance' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
    'block/activity_copy_cart:copyactivities' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        // Matches moodle/restore:restoreactivity: this capability can bring
        // activity content and (optionally) user data across course boundaries.
        'riskbitmask' => RISK_SPAM | RISK_PERSONAL | RISK_XSS,
        'clonepermissionsfrom' => 'moodle/restore:restoreactivity',
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
];
