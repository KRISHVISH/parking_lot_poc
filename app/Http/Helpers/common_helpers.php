<?php

if (!function_exists('invalidRequest')) {
    function invalidRequest($message = '')
    {
        $message = $message ?? "Invalid Request";
        return response()->json(["message" => $message],400);
    }
}

if (!function_exists('error_response')) {
    function error_response($message = '')
    {
        $message = $message ?? "Something went wrong";
        return response()->json(["message" => $message]);
    }
}

if (!function_exists('success_response')) {
    function success_response($options = [])
    {
        $responseArr['message'] = $options['message'] ?? "Success!";
        $responseArr['data'] = $options['data'] ?? null;
        return response()->json($responseArr);
    }
}
