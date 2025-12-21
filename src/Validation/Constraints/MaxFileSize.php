<?php

/**
 * ═══════════════════════════════════════════════════════════════════════
 * 📏 MAXFILESIZE - Contrainte de Taille Maximale de Fichier
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * EXEMPLE :
 * ---------
 * new MaxFileSize('5M')     // 5 Mo
 * new MaxFileSize('500K')   // 500 Ko
 * new MaxFileSize(5242880)  // 5 Mo en bytes
 * 
 * ═══════════════════════════════════════════════════════════════════════
 */

namespace Ogan\Validation\Constraints;

use Ogan\Http\UploadedFile;

class MaxFileSize implements FileConstraintInterface
{
    private int $maxBytes;
    private string $message;

    public function __construct(string|int $maxSize, ?string $message = null)
    {
        $this->maxBytes = $this->parseSize($maxSize);
        $this->message = $message ?? 'Le fichier ne doit pas dépasser %size%.';
    }

    public function validate(UploadedFile $file): ?string
    {
        if (!$file->isValid()) {
            return null; // Laisse les autres validations gérer
        }

        if ($file->getSize() > $this->maxBytes) {
            return str_replace('%size%', $this->formatSize($this->maxBytes), $this->message);
        }

        return null;
    }

    /**
     * Parse une taille type "5M", "500K" en bytes
     */
    private function parseSize(string|int $size): int
    {
        if (is_int($size)) {
            return $size;
        }

        $size = strtoupper(trim($size));
        $number = (float) preg_replace('/[^0-9.]/', '', $size);
        $unit = preg_replace('/[0-9.]/', '', $size);

        return match ($unit) {
            'K', 'KB' => (int) ($number * 1024),
            'M', 'MB' => (int) ($number * 1024 * 1024),
            'G', 'GB' => (int) ($number * 1024 * 1024 * 1024),
            default => (int) $number,
        };
    }

    private function formatSize(int $bytes): string
    {
        $units = ['o', 'Ko', 'Mo', 'Go'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
