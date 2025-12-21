<?php

/**
 * ═══════════════════════════════════════════════════════════════════════
 * 📋 FILECONSTRAINTINTERFACE - Interface des Contraintes de Fichier
 * ═══════════════════════════════════════════════════════════════════════
 */

namespace Ogan\Validation\Constraints;

use Ogan\Http\UploadedFile;

interface FileConstraintInterface
{
    /**
     * Valide un fichier uploadé
     * 
     * @param UploadedFile $file Fichier à valider
     * @return string|null Message d'erreur ou null si valide
     */
    public function validate(UploadedFile $file): ?string;
}
