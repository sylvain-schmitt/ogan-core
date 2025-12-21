<?php

/**
 * ═══════════════════════════════════════════════════════════════════════
 * 📎 MIMETYPE - Contrainte de Type MIME de Fichier
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * EXEMPLE :
 * ---------
 * new MimeType(['image/jpeg', 'image/png', 'image/webp'])
 * new MimeType(['application/pdf'])
 * 
 * ═══════════════════════════════════════════════════════════════════════
 */

namespace Ogan\Validation\Constraints;

use Ogan\Http\UploadedFile;

class MimeType implements FileConstraintInterface
{
    private array $allowedTypes;
    private string $message;

    public function __construct(array $allowedTypes, ?string $message = null)
    {
        $this->allowedTypes = $allowedTypes;
        $this->message = $message ?? 'Le type de fichier n\'est pas autorisé. Types acceptés : %types%.';
    }

    public function validate(UploadedFile $file): ?string
    {
        if (!$file->isValid()) {
            return null;
        }

        $mimeType = $file->getMimeType();

        // Vérification exacte
        if (in_array($mimeType, $this->allowedTypes, true)) {
            return null;
        }

        // Vérification avec wildcards (ex: image/*)
        foreach ($this->allowedTypes as $allowed) {
            if (str_ends_with($allowed, '/*')) {
                $prefix = rtrim($allowed, '*');
                if (str_starts_with($mimeType, $prefix)) {
                    return null;
                }
            }
        }

        return str_replace('%types%', implode(', ', $this->allowedTypes), $this->message);
    }
}
