<?php
class EmailService {
    private $smtpHost = 'smtp.gmail.com';
    private $smtpPort = 587;
    private $smtpUsername = 'je4stine@gmail.com';
    private $smtpPassword = 'tnizufprkbqcgvry';
    private $fromEmail = 'je4stine@gmail.com';
    private $fromName = 'ICJ Kenya';
    
    public function sendPasswordResetEmail($toEmail, $resetToken) {
        $subject = 'Password Reset Request - ICJ Kenya';
        $resetLink = "https://icjkenya.netlify.app/reset-password?token=" . $resetToken;
        
        $body = $this->getPasswordResetTemplate($resetLink);
        
        return $this->sendEmail($toEmail, $subject, $body);
    }
    
    public function sendWelcomeEmail($toEmail, $firstName) {
        $subject = 'Welcome to ICJ Kenya!';
        $body = $this->getWelcomeTemplate($firstName);
        
        return $this->sendEmail($toEmail, $subject, $body);
    }
    
    private function sendEmail($to, $subject, $body, $isHtml = true) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: ' . ($isHtml ? 'text/html' : 'text/plain') . '; charset=UTF-8',
            'From: ' . $this->fromName . ' <' . $this->fromEmail . '>',
            'Reply-To: ' . $this->fromEmail,
            'X-Mailer: PHP/' . phpversion()
        ];
        
        // For production, you should use a proper mail library like PHPMailer or SwiftMailer
        // This is a basic implementation using PHP's mail() function
        
        try {
            $success = mail($to, $subject, $body, implode("\r\n", $headers));
            
            if (!$success) {
                throw new Exception('Failed to send email');
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log('Email sending failed: ' . $e->getMessage());
            return false;
        }
    }
    
    private function getPasswordResetTemplate($resetLink) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Password Reset</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #007bff; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .button { 
                    display: inline-block; 
                    background-color: #007bff; 
                    color: white; 
                    padding: 12px 24px; 
                    text-decoration: none; 
                    border-radius: 4px; 
                    margin: 20px 0; 
                }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Password Reset Request</h1>
                </div>
                <div class='content'>
                    <p>Hello,</p>
                    <p>You have requested to reset your password for your ICJ Kenya account.</p>
                    <p>Click the button below to reset your password:</p>
                    <a href='{$resetLink}' class='button'>Reset Password</a>
                    <p>If you did not request this password reset, please ignore this email.</p>
                    <p>This link will expire in 24 hours for security reasons.</p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " ICJ Kenya. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getWelcomeTemplate($firstName) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Welcome to ICJ Kenya</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #28a745; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Welcome to ICJ Kenya!</h1>
                </div>
                <div class='content'>
                    <p>Hello {$firstName},</p>
                    <p>Welcome to ICJ Kenya! Your account has been successfully created.</p>
                    <p>You can now:</p>
                    <ul>
                        <li>Create and share posts</li>
                        <li>Join discussion forums</li>
                        <li>Connect with other members</li>
                        <li>Participate in conversations</li>
                    </ul>
                    <p>Thank you for joining our community!</p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " ICJ Kenya. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
    }
}
