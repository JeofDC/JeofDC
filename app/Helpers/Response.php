<?php
// app/Helpers/Response.php

class Response {
    public static function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    public static function error($message, $statusCode = 400, $errors = []) {
        self::json([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], $statusCode);
    }

    public static function success($data = [], $message = 'Success') {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }
}
