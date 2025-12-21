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

    /**
     * Retourne le chemin relatif pour le web (sans "public/")
     */
    public function getWebPath(): string
    {
        return str_replace('public/', '', $this->path);
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
