<?php

namespace App;

class ResponseMessageService
{
    public function error($message, $code = 400)
    {
        return response()->json(['success' => false, 'error' => $message], $code);
    }
    public function success($message, $code = 200)
    {
        return response()->json(['success' => true, 'message' => $message], $code);
    }

}