<?php
class FileUploadHelper {
    const MAX_IMAGE_SIZE = 10 * 1024 * 1024; // 10MB
    const MAX_DOCUMENT_SIZE = 20 * 1024 * 1024; // 20MB
    
    const ALLOWED_IMAGE_TYPES = [
        'image/jpeg',
        'image/png', 
        'image/gif',
        'image/webp'
    ];
    
    const ALLOWED_DOCUMENT_TYPES = [
        'application/pdf'
    ];
    
    public static function validateImageFile($file) {
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return true; // File is optional
        }
        
        if (!in_array($file['type'], self::ALLOWED_IMAGE_TYPES)) {
            throw new Exception('Invalid image file type. Allowed: JPEG, PNG, GIF, WebP');
        }
        
        if ($file['size'] > self::MAX_IMAGE_SIZE) {
            throw new Exception('Image file too large. Maximum size: 10MB');
        }
        
        // Additional validation: check actual file content
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            throw new Exception('Invalid image file');
        }
        
        return true;
    }
    
    public static function validateDocumentFile($file) {
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return true; // File is optional
        }
        
        if (!in_array($file['type'], self::ALLOWED_DOCUMENT_TYPES)) {
            throw new Exception('Invalid document file type. Only PDF allowed');
        }
        
        if ($file['size'] > self::MAX_DOCUMENT_SIZE) {
            throw new Exception('Document file too large. Maximum size: 20MB');
        }
        
        return true;
    }
    
    public static function compressPdf($filePath, $outputPath = null) {
        // Basic PDF compression using shell command (requires Ghostscript)
        // In production, you might want to use a PHP library like TCPDF or similar
        
        if (!$outputPath) {
            $outputPath = $filePath;
        }
        
        $command = "gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/screen -dNOPAUSE -dQUIET -dBATCH -sOutputFile={$outputPath} {$filePath}";
        
        try {
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0) {
                return $outputPath;
            } else {
                // If compression fails, return original file
                return $filePath;
            }
        } catch (Exception $e) {
            // If compression fails, return original file
            return $filePath;
        }
    }
    
    public static function resizeImage($filePath, $maxWidth = 1920, $maxHeight = 1080, $quality = 85) {
        $imageInfo = getimagesize($filePath);
        if ($imageInfo === false) {
            return $filePath;
        }
        
        $originalWidth = $imageInfo[0];
        $originalHeight = $imageInfo[1];
        $imageType = $imageInfo[2];
        
        // Calculate new dimensions
        $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
        
        if ($ratio >= 1) {
            return $filePath; // No need to resize
        }
        
        $newWidth = round($originalWidth * $ratio);
        $newHeight = round($originalHeight * $ratio);
        
        // Create image from file
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                $sourceImage = imagecreatefromjpeg($filePath);
                break;
            case IMAGETYPE_PNG:
                $sourceImage = imagecreatefrompng($filePath);
                break;
            case IMAGETYPE_GIF:
                $sourceImage = imagecreatefromgif($filePath);
                break;
            case IMAGETYPE_WEBP:
                $sourceImage = imagecreatefromwebp($filePath);
                break;
            default:
                return $filePath;
        }
        
        if (!$sourceImage) {
            return $filePath;
        }
        
        // Create new image
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG and GIF
        if ($imageType == IMAGETYPE_PNG || $imageType == IMAGETYPE_GIF) {
            imagecolortransparent($newImage, imagecolorallocatealpha($newImage, 0, 0, 0, 127));
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
        }
        
        // Resize image
        imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
        
        // Save resized image
        $tempFile = tempnam(sys_get_temp_dir(), 'resized_image_');
        
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                imagejpeg($newImage, $tempFile, $quality);
                break;
            case IMAGETYPE_PNG:
                imagepng($newImage, $tempFile);
                break;
            case IMAGETYPE_GIF:
                imagegif($newImage, $tempFile);
                break;
            case IMAGETYPE_WEBP:
                imagewebp($newImage, $tempFile, $quality);
                break;
        }
        
        // Clean up
        imagedestroy($sourceImage);
        imagedestroy($newImage);
        
        return $tempFile;
    }
    
    public static function getFileExtension($filename) {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }
    
    public static function generateUniqueFilename($originalFilename) {
        $extension = self::getFileExtension($originalFilename);
        return uniqid('file_', true) . '.' . $extension;
    }
}
