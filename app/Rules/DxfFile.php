<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class DxfFile implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$value instanceof UploadedFile) {
            $fail('The :attribute must be a file.');
            return;
        }

        if (!$value->isValid()) {
            $fail('The :attribute is not a valid file.');
            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());
        $mimeType = $value->getMimeType();

        // Check file extension
        if ($extension !== 'dxf') {
            $fail('The :attribute must be a DXF file.');
            return;
        }

        // Check MIME type (DXF files can have various MIME types)
        $validMimeTypes = [
            'application/dxf',
            'application/octet-stream',
            'text/plain',
            'application/autocad_dwg',
            'image/vnd.dxf',
            'application/x-dxf',
            'text/dxf'
        ];

        if (!in_array($mimeType, $validMimeTypes) && $mimeType !== null) {
            $fail('The :attribute must be a valid DXF file format.');
            return;
        }

        // Additional check: try to read the file content to verify it's a DXF
        try {
            $content = file_get_contents($value->getRealPath());
            if (!$this->isDxfContent($content)) {
                $fail('The :attribute does not appear to be a valid DXF file.');
                return;
            }
        } catch (\Exception $e) {
            $fail('The :attribute could not be read.');
            return;
        }
    }

    /**
     * Check if the file content appears to be DXF format
     *
     * @param string $content
     * @return bool
     */
    private function isDxfContent(string $content): bool
    {
        // DXF files typically start with specific patterns
        $content = trim($content);
        
        // Check for common DXF file patterns
        $dxfPatterns = [
            '/^0\s*\nSECTION\s*\n2\s*\nHEADER\s*\n/i',  // DXF header section
            '/^0\s*\nSECTION\s*\n2\s*\nENTITIES\s*\n/i', // DXF entities section
            '/^0\s*\nSECTION\s*\n2\s*\nOBJECTS\s*\n/i',   // DXF objects section
            '/^0\s*\nEOF\s*\n$/i',                        // DXF end of file
        ];

        foreach ($dxfPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        // If no specific patterns found, check if it contains DXF-like structure
        // (group codes followed by values)
        if (preg_match('/^\d+\s*\n.+\s*\n/m', $content)) {
            return true;
        }

        return false;
    }
} 