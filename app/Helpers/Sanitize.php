<?php
// app/Helpers/Sanitize.php

class Sanitize {
    public static function string($input) {
        if (!is_string($input)) return '';
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    public static function email($input) {
        if (!is_string($input)) return '';
        return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
    }

    public static function int($input) {
        return filter_var($input, FILTER_VALIDATE_INT);
    }
}
