<?php
class JWTService {
    private $secretKey = JWT_SECRET;
    private $algorithm = 'HS256';
    private $expirationTime = JWT_EXPIRATION;
    
    public function generateToken($email) {
        $header = json_encode(['typ' => 'JWT', 'alg' => $this->algorithm]);
        
        $payload = json_encode([
            'email' => $email,
            'iat' => time(),
            'exp' => time() + $this->expirationTime
        ]);
        
        $base64Header = $this->base64UrlEncode($header);
        $base64Payload = $this->base64UrlEncode($payload);
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $this->secretKey, true);
        $base64Signature = $this->base64UrlEncode($signature);
        
        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }
    
    public function verifyToken($token) {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            throw new Exception('Invalid token format');
        }
        
        [$header, $payload, $signature] = $parts;
        
        $expectedSignature = hash_hmac('sha256', $header . "." . $payload, $this->secretKey, true);
        $expectedSignature = $this->base64UrlEncode($expectedSignature);
        
        if ($signature !== $expectedSignature) {
            throw new Exception('Invalid token signature');
        }
        
        $decodedPayload = json_decode($this->base64UrlDecode($payload), true);
        
        if ($decodedPayload['exp'] < time()) {
            throw new Exception('Token has expired');
        }
        
        return $decodedPayload;
    }
    
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    private function base64UrlDecode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}
