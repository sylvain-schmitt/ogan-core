<?php

/**
 * ═══════════════════════════════════════════════════════════════════════
 * 🖼️ OPTIMIZEDIMAGE - Résultat d'une Optimisation d'Image
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * RÔLE :
 * ------
 * DTO (Data Transfer Object) contenant les informations d'une image
 * après optimisation.
 * 
 * ═══════════════════════════════════════════════════════════════════════
 */

namespace Ogan\Image;

readonly class OptimizedImage
{
    public function __construct(
        public string $path,
        public string $filename,
        public int $width,
        public int $height,
        public int $size,
        public string $format
    ) {}

    // ═══════════════════════════════════════════════════════════════════
    // GETTERS (pour compatibilité avec les moteurs de template)
    // ═══════════════════════════════════════════════════════════════════

    public function getPath(): string
    {
        return $this->path;
    }
    public function getFilename(): string
    {
        return $this->filename;
    }
    public function getWidth(): int
    {
        return $this->width;
    }
    public function getHeight(): int
    {
        return $this->height;
    }
    public function getSize(): int
    {
        return $this->size;
    }
    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * Retourne le chemin relatif pour le web (sans "public/")
     * Gère les chemins absolus et relatifs
     */
    public function getWebPath(): string
    {
        // Si le chemin contient 'public/', on extrait ce qui suit
        if (str_contains($this->path, 'public/')) {
            $parts = explode('public/', $this->path, 2);
            return $parts[1] ?? $this->path;
        }

        return $this->path;
    }

    /**
     * Retourne la taille formatée
     */
    public function getFormattedSize(): string
    {
        $bytes = $this->size;
        $units = ['o', 'Ko', 'Mo', 'Go'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Retourne les dimensions sous forme de chaîne
     */
    public function getDimensions(): string
    {
        return $this->width . 'x' . $this->height;
    }

    /**
     * Conversion en tableau
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'web_path' => $this->getWebPath(),
            'filename' => $this->filename,
            'width' => $this->width,
            'height' => $this->height,
            'size' => $this->size,
            'formatted_size' => $this->getFormattedSize(),
            'format' => $this->format,
        ];
    }
}
