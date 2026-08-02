<?php

namespace block_activity_copy_cart\app\block;

final class item_settings_test extends \basic_testcase {

    public function test_sanitize_returns_allowed_value(): void {
        $this->assertSame(
            item_settings::SECTION_MATCH_POSITION,
            item_settings::sanitize('sectionmatch', item_settings::SECTION_MATCH_POSITION)
        );
    }

    public function test_sanitize_falls_back_to_default_on_null(): void {
        $this->assertSame(
            item_settings::DEFAULTS['visibility'],
            item_settings::sanitize('visibility', null)
        );
    }

    public function test_sanitize_falls_back_to_default_on_invalid_value(): void {
        $this->assertSame(
            item_settings::DEFAULTS['nameconflict'],
            item_settings::sanitize('nameconflict', 'not-a-real-option')
        );
    }

    public function test_every_allowed_value_round_trips(): void {
        foreach (item_settings::ALLOWED_VALUES as $field => $allowed) {
            foreach ($allowed as $value) {
                $this->assertSame($value, item_settings::sanitize($field, $value));
            }
            $this->assertContains(
                item_settings::DEFAULTS[$field],
                $allowed,
                "Default for '{$field}' must be one of its own allowed values."
            );
        }
    }

    public function test_sanitize_unknown_field_throws(): void {
        $this->expectException(\coding_exception::class);
        item_settings::sanitize('not-a-real-field', 'whatever');
    }
}
