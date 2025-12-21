<?php

/**
 * ═══════════════════════════════════════════════════════════════════════
 * 🖼️ IMAGEOPTIMIZER - Service d'Optimisation d'Images
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * RÔLE :
 * ------
 * Optimise automatiquement les images uploadées :
 * - Redimensionnement (respecte le ratio)
 * - Compression (qualité configurable)
 * - Conversion en WebP
 * - Génération de thumbnails multiples
 * 
 * EXEMPLE D'UTILISATION :
 * ------------------------
 * $optimizer = new ImageOptimizer();
 * 
 * // Optimisation simple
 * $result = $optimizer->optimize($uploadedFile, [
 *     'maxWidth' => 1920,
 *     'quality' => 85,
 *     'format' => 'webp'
 * ]);
 * 
 * // Avec thumbnails
 * $results = $optimizer->optimizeWithThumbnails($uploadedFile, [
 *     'thumbnail' => ['width' => 150, 'height' => 150, 'crop' => true],
 *     'medium' => ['width' => 600],
 *     'large' => ['width' => 1200],
 * ]);
 * 
 * ═══════════════════════════════════════════════════════════════════════
 */

namespace Ogan\Image;

use Ogan\Http\UploadedFile;
use Ogan\Config\Config;

class ImageOptimizer
{
    private string $uploadDir;
    private int $defaultQuality;
    private string $defaultFormat;

    public function __construct()
    {
        // Configuration depuis parameters.yaml (avec defauts)
        $this->uploadDir = Config::get('uploads.directory', 'public/uploads');
        $this->defaultQuality = (int) Config::get('uploads.quality', 85);
        $this->defaultFormat = Config::get('uploads.format', 'webp');
    }

    /**
     * Optimise une image uploadée
     * 
     * @param UploadedFile $file Fichier uploadé
     * @param array $options Options d'optimisation
     * @return OptimizedImage Résultat de l'optimisation
     */
    public function optimize(UploadedFile $file, array $options = []): OptimizedImage
    {
        if (!$file->isValid()) {
            throw new \RuntimeException('Fichier invalide: ' . $file->getErrorMessage());
        }

        if (!$file->isImage()) {
            throw new \RuntimeException('Le fichier n\'est pas une image');
        }

        $maxWidth = $options['maxWidth'] ?? null;
        $maxHeight = $options['maxHeight'] ?? null;
        $quality = $options['quality'] ?? $this->defaultQuality;
        $format = $options['format'] ?? $this->defaultFormat;
        $directory = $options['directory'] ?? $this->uploadDir;
        $filename = $options['filename'] ?? null;

        // Charger l'image source
        $source = $this->loadImage($file->getTempPath());
        if ($source === null) {
            throw new \RuntimeException('Impossible de charger l\'image');
        }

        $originalWidth = imagesx($source);
        $originalHeight = imagesy($source);

        // Calculer les nouvelles dimensions
        [$newWidth, $newHeight] = $this->calculateDimensions(
            $originalWidth,
            $originalHeight,
            $maxWidth,
            $maxHeight
        );

        // Redimensionner si nécessaire
        if ($newWidth !== $originalWidth || $newHeight !== $originalHeight) {
            $resized = imagecreatetruecolor($newWidth, $newHeight);

            // Préserver la transparence pour PNG et WebP
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);

            imagecopyresampled(
                $resized,
                $source,
                0,
                0,
                0,
                0,
                $newWidth,
                $newHeight,
                $originalWidth,
                $originalHeight
            );

            imagedestroy($source);
            $source = $resized;
        }

        // Générer le nom de fichier
        if ($filename === null) {
            $filename = $file->generateUniqueFilename($format);
        } elseif (!str_ends_with($filename, '.' . $format)) {
            $filename = pathinfo($filename, PATHINFO_FILENAME) . '.' . $format;
        }

        // Créer le dossier si nécessaire
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $destination = rtrim($directory, '/') . '/' . $filename;

        // Sauvegarder dans le format demandé
        $this->saveImage($source, $destination, $format, $quality);
        imagedestroy($source);

        return new OptimizedImage(
            path: $destination,
            filename: $filename,
            width: $newWidth,
            height: $newHeight,
            size: filesize($destination),
            format: $format
        );
    }

    /**
     * Optimise une image avec génération de thumbnails
     * 
     * @param UploadedFile $file Fichier uploadé
     * @param array $sizes Tailles à générer ['name' => ['width' => X, 'height' => Y, 'crop' => bool]]
     * @param array $options Options globales
     * @return array<string, OptimizedImage> Résultats par nom de taille
     */
    public function optimizeWithThumbnails(UploadedFile $file, ?array $sizes = null, array $options = []): array
    {
        // Tailles par défaut si non spécifiées
        $sizes = $sizes ?? [
            'thumbnail' => ['width' => 150, 'height' => 150, 'crop' => true],
            'medium' => ['width' => 600],
            'large' => ['width' => 1200],
        ];

        $results = [];
        $quality = $options['quality'] ?? $this->defaultQuality;
        $format = $options['format'] ?? $this->defaultFormat;
        $directory = $options['directory'] ?? $this->uploadDir;
        $baseFilename = $options['filename'] ?? $file->generateUniqueFilename($format);
        $baseName = pathinfo($baseFilename, PATHINFO_FILENAME);

        // Image originale optimisée (version "large" ou max)
        $results['original'] = $this->optimize($file, array_merge($options, [
            'filename' => $baseName . '.' . $format,
        ]));

        // Générer chaque taille
        foreach ($sizes as $sizeName => $sizeOptions) {
            $width = $sizeOptions['width'] ?? null;
            $height = $sizeOptions['height'] ?? null;
            $crop = $sizeOptions['crop'] ?? false;

            $sizeFilename = $baseName . '_' . $sizeName . '.' . $format;

            if ($crop && $width && $height) {
                $results[$sizeName] = $this->cropAndResize(
                    $file,
                    $width,
                    $height,
                    $directory,
                    $sizeFilename,
                    $format,
                    $quality
                );
            } else {
                $results[$sizeName] = $this->optimize($file, [
                    'maxWidth' => $width,
                    'maxHeight' => $height,
                    'quality' => $quality,
                    'format' => $format,
                    'directory' => $directory,
                    'filename' => $sizeFilename,
                ]);
            }
        }

        return $results;
    }

    /**
     * Recadre et redimensionne une image (pour les thumbnails carrés)
     */
    private function cropAndResize(
        UploadedFile $file,
        int $targetWidth,
        int $targetHeight,
        string $directory,
        string $filename,
        string $format,
        int $quality
    ): OptimizedImage {
        $source = $this->loadImage($file->getTempPath());
        $originalWidth = imagesx($source);
        $originalHeight = imagesy($source);

        // Calculer le ratio pour le crop centré
        $sourceRatio = $originalWidth / $originalHeight;
        $targetRatio = $targetWidth / $targetHeight;

        if ($sourceRatio > $targetRatio) {
            // Image plus large que le ratio cible
            $cropHeight = $originalHeight;
            $cropWidth = (int) ($originalHeight * $targetRatio);
            $cropX = (int) (($originalWidth - $cropWidth) / 2);
            $cropY = 0;
        } else {
            // Image plus haute que le ratio cible
            $cropWidth = $originalWidth;
            $cropHeight = (int) ($originalWidth / $targetRatio);
            $cropX = 0;
            $cropY = (int) (($originalHeight - $cropHeight) / 2);
        }

        // Créer l'image de destination
        $destination = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($destination, false);
        imagesavealpha($destination, true);

        // Crop et redimensionnement
        imagecopyresampled(
            $destination,
            $source,
            0,
            0,
            $cropX,
            $cropY,
            $targetWidth,
            $targetHeight,
            $cropWidth,
            $cropHeight
        );

        imagedestroy($source);

        // Créer le dossier si nécessaire
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = rtrim($directory, '/') . '/' . $filename;
        $this->saveImage($destination, $path, $format, $quality);
        imagedestroy($destination);

        return new OptimizedImage(
            path: $path,
            filename: $filename,
            width: $targetWidth,
            height: $targetHeight,
            size: filesize($path),
            format: $format
        );
    }

    /**
     * Charge une image depuis un chemin
     */
    private function loadImage(string $path): ?\GdImage
    {
        $mime = mime_content_type($path);

        return match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png' => imagecreatefrompng($path),
            'image/gif' => imagecreatefromgif($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : null,
            default => null,
        };
    }

    /**
     * Sauvegarde une image dans le format demandé
     */
    private function saveImage(\GdImage $image, string $path, string $format, int $quality): void
    {
        match ($format) {
            'jpeg', 'jpg' => imagejpeg($image, $path, $quality),
            'png' => imagepng($image, $path, (int) (9 - ($quality / 100) * 9)),
            'gif' => imagegif($image, $path),
            'webp' => imagewebp($image, $path, $quality),
            default => throw new \RuntimeException('Format non supporté: ' . $format),
        };
    }

    /**
     * Calcule les nouvelles dimensions en respectant le ratio
     */
    private function calculateDimensions(
        int $originalWidth,
        int $originalHeight,
        ?int $maxWidth,
        ?int $maxHeight
    ): array {
        $newWidth = $originalWidth;
        $newHeight = $originalHeight;

        if ($maxWidth !== null && $originalWidth > $maxWidth) {
            $ratio = $maxWidth / $originalWidth;
            $newWidth = $maxWidth;
            $newHeight = (int) ($originalHeight * $ratio);
        }

        if ($maxHeight !== null && $newHeight > $maxHeight) {
            $ratio = $maxHeight / $newHeight;
            $newHeight = $maxHeight;
            $newWidth = (int) ($newWidth * $ratio);
        }

        return [$newWidth, $newHeight];
    }

    /**
     * Retourne le dossier d'upload configuré
     */
    public function getUploadDirectory(): string
    {
        return $this->uploadDir;
    }
}
