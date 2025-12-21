<?php

/**
 * ═══════════════════════════════════════════════════════════════════════
 * 🖼️ IMAGEDIMENSIONS - Contrainte de Dimensions d'Image
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * EXEMPLE :
 * ---------
 * new ImageDimensions(['minWidth' => 800, 'maxWidth' => 4000])
 * new ImageDimensions(['minHeight' => 600])
 * new ImageDimensions(['maxWidth' => 1920, 'maxHeight' => 1080])
 * 
 * ═══════════════════════════════════════════════════════════════════════
 */

namespace Ogan\Validation\Constraints;

use Ogan\Http\UploadedFile;

class ImageDimensions implements FileConstraintInterface
{
    private ?int $minWidth;
    private ?int $maxWidth;
    private ?int $minHeight;
    private ?int $maxHeight;
    private string $message;

    public function __construct(array $options, ?string $message = null)
    {
        $this->minWidth = $options['minWidth'] ?? null;
        $this->maxWidth = $options['maxWidth'] ?? null;
        $this->minHeight = $options['minHeight'] ?? null;
        $this->maxHeight = $options['maxHeight'] ?? null;
        $this->message = $message ?? 'Les dimensions de l\'image ne sont pas valides.';
    }

    public function validate(UploadedFile $file): ?string
    {
        if (!$file->isValid() || !$file->isImage()) {
            return null;
        }

        $dimensions = $file->getImageDimensions();
        if ($dimensions === null) {
            return 'Impossible de lire les dimensions de l\'image.';
        }

        $width = $dimensions['width'];
        $height = $dimensions['height'];
        $errors = [];

        if ($this->minWidth !== null && $width < $this->minWidth) {
            $errors[] = "Largeur minimum : {$this->minWidth}px (actuel : {$width}px)";
        }

        if ($this->maxWidth !== null && $width > $this->maxWidth) {
            $errors[] = "Largeur maximum : {$this->maxWidth}px (actuel : {$width}px)";
        }

        if ($this->minHeight !== null && $height < $this->minHeight) {
            $errors[] = "Hauteur minimum : {$this->minHeight}px (actuel : {$height}px)";
        }

        if ($this->maxHeight !== null && $height > $this->maxHeight) {
            $errors[] = "Hauteur maximum : {$this->maxHeight}px (actuel : {$height}px)";
        }

        return empty($errors) ? null : implode('. ', $errors);
    }
}
