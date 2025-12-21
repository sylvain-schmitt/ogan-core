<?php

/**
 * ═══════════════════════════════════════════════════════════════════════
 * 📁 UPLOADEDFILE - Classe Wrapper pour les Fichiers Uploadés
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * RÔLE :
 * ------
 * Encapsule les informations d'un fichier uploadé via $_FILES et fournit
 * une API fluide pour manipuler le fichier.
 * 
 * EXEMPLE D'UTILISATION :
 * ------------------------
 * $file = $request->file('image');
 * 
 * if ($file && $file->isValid()) {
 *     $file->move('uploads/articles/', 'mon-image.jpg');
 * }
 * 
 * ═══════════════════════════════════════════════════════════════════════
 */

namespace Ogan\Http;

class UploadedFile
{
    private string $originalName;
    private string $mimeType;
    private string $tmpPath;
    private int $size;
    private int $error;

    /**
     * Crée une instance depuis les données $_FILES
     */
    public function __construct(array $fileData)
    {
        $this->originalName = $fileData['name'] ?? '';
        $this->mimeType = $fileData['type'] ?? '';
        $this->tmpPath = $fileData['tmp_name'] ?? '';
        $this->size = (int) ($fileData['size'] ?? 0);
        $this->error = (int) ($fileData['error'] ?? UPLOAD_ERR_NO_FILE);
    }

    /**
     * Vérifie si le fichier a été uploadé sans erreur
     */
    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK && is_uploaded_file($this->tmpPath);
    }

    /**
     * Retourne le nom original du fichier
     */
    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    /**
     * Retourne l'extension du fichier (sans le point)
     */
    public function getExtension(): string
    {
        return strtolower(pathinfo($this->originalName, PATHINFO_EXTENSION));
    }

    /**
     * Retourne le nom du fichier sans extension
     */
    public function getBasename(): string
    {
        return pathinfo($this->originalName, PATHINFO_FILENAME);
    }

    /**
     * Retourne le type MIME du fichier
     */
    public function getMimeType(): string
    {
        // Utiliser finfo pour une détection plus fiable
        if ($this->isValid() && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $this->tmpPath);
            finfo_close($finfo);
            return $mimeType ?: $this->mimeType;
        }
        return $this->mimeType;
    }

    /**
     * Retourne la taille du fichier en bytes
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Retourne la taille formatée (Ko, Mo, etc.)
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
     * Retourne le chemin temporaire du fichier
     */
    public function getTempPath(): string
    {
        return $this->tmpPath;
    }

    /**
     * Retourne le code d'erreur
     */
    public function getError(): int
    {
        return $this->error;
    }

    /**
     * Retourne un message d'erreur lisible
     */
    public function getErrorMessage(): string
    {
        return match ($this->error) {
            UPLOAD_ERR_OK => 'Fichier uploadé avec succès',
            UPLOAD_ERR_INI_SIZE => 'Le fichier dépasse la taille maximale autorisée par PHP',
            UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la taille maximale autorisée par le formulaire',
            UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été que partiellement uploadé',
            UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été uploadé',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant',
            UPLOAD_ERR_CANT_WRITE => 'Échec de l\'écriture du fichier sur le disque',
            UPLOAD_ERR_EXTENSION => 'Une extension PHP a arrêté l\'upload',
            default => 'Erreur inconnue',
        };
    }

    /**
     * Vérifie si le fichier est une image
     */
    public function isImage(): bool
    {
        return str_starts_with($this->getMimeType(), 'image/');
    }

    /**
     * Retourne les dimensions de l'image (si c'est une image)
     * 
     * @return array|null ['width' => int, 'height' => int] ou null
     */
    public function getImageDimensions(): ?array
    {
        if (!$this->isValid() || !$this->isImage()) {
            return null;
        }

        $size = getimagesize($this->tmpPath);
        if ($size === false) {
            return null;
        }

        return [
            'width' => $size[0],
            'height' => $size[1],
        ];
    }

    /**
     * Déplace le fichier vers sa destination finale
     * 
     * @param string $directory Dossier de destination
     * @param string|null $filename Nom du fichier (auto-généré si null)
     * @return string Chemin complet du fichier déplacé
     * @throws \RuntimeException Si le déplacement échoue
     */
    public function move(string $directory, ?string $filename = null): string
    {
        if (!$this->isValid()) {
            throw new \RuntimeException('Impossible de déplacer un fichier invalide: ' . $this->getErrorMessage());
        }

        // Créer le dossier si nécessaire
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                throw new \RuntimeException('Impossible de créer le dossier: ' . $directory);
            }
        }

        // Générer un nom unique si non fourni
        if ($filename === null) {
            $filename = uniqid() . '_' . $this->sanitizeFilename($this->originalName);
        }

        $destination = rtrim($directory, '/') . '/' . $filename;

        if (!move_uploaded_file($this->tmpPath, $destination)) {
            throw new \RuntimeException('Échec du déplacement du fichier vers: ' . $destination);
        }

        return $destination;
    }

    /**
     * Nettoie le nom de fichier pour éviter les problèmes de sécurité
     */
    private function sanitizeFilename(string $filename): string
    {
        // Supprimer les caractères dangereux
        $filename = preg_replace('/[^\w\-.]/', '_', $filename);
        // Éviter les noms cachés (commençant par .)
        $filename = ltrim($filename, '.');
        // Limiter la longueur
        if (strlen($filename) > 100) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $name = pathinfo($filename, PATHINFO_FILENAME);
            $filename = substr($name, 0, 95) . '.' . $ext;
        }
        return $filename ?: 'file';
    }

    /**
     * Génère un nom de fichier unique
     */
    public function generateUniqueFilename(?string $extension = null): string
    {
        $ext = $extension ?? $this->getExtension();
        return uniqid() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    }
}
