<?php
class ResponseHelper {
    public static function success($data = null, $message = null, $statusCode = 200) {
        http_response_code($statusCode);
        $response = [];
        
        if ($message) {
            $response['message'] = $message;
        }
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        echo json_encode($response);
        exit;
    }
    
    public static function error($message, $statusCode = 400, $errors = null) {
        http_response_code($statusCode);
        $response = ['error' => $message];
        
        if ($errors) {
            $response['errors'] = $errors;
        }
        
        echo json_encode($response);
        exit;
    }
    
    public static function paginated($data, $total, $page, $pageSize) {
        $totalPages = ceil($total / $pageSize);

        http_response_code(200);
        echo json_encode([
            'content' => $data,
            'totalElements' => $total,
            'totalPages' => $totalPages,
            'number' => $page,
            'size' => $pageSize,
            'first' => $page === 0,
            'last' => $page === $totalPages - 1
        ]);
        exit;
    }
}
