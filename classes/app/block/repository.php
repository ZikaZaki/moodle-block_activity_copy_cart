<?php

namespace block_activity_copy_cart\app\block;


/**
 * Session storage for the drag-and-drop cart.
 */
final class repository {
    /** @var string Session key the parsed cart is stored under. */
    const SESSION_KEY = 'block_activity_copy_cart_cart';

    /**
     * Stores a freshly parsed cart.
     *
     * @param array $cart As returned by \block_activity_copy_cart\app\block\manager::from_submitted_data()
     */
    public static function save(array $cart): void {
        global $SESSION;
        $SESSION->{self::SESSION_KEY} = $cart;
    }

    /**
     * Reads the current cart back out.
     *
     * @return array|null Null if no cart has been stored (or it was cleared)
     */
    public static function get(): ?array {
        global $SESSION;
        return $SESSION->{self::SESSION_KEY} ?? null;
    }

    /**
     * Clears the cart, ending the current copy wizard.
     */
    public static function clear(): void {
        global $SESSION;
        unset($SESSION->{self::SESSION_KEY});
    }
}
