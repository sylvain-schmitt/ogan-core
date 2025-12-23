<?php

/**
 * ═══════════════════════════════════════════════════════════════════════
 * 📦 FIELDTYPETRAIT - Implémentations par défaut pour les types de champs
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * Ce trait fournit les implémentations par défaut de renderLabel() et
 * renderErrors() pour éviter la duplication de code dans les types.
 * 
 * ═══════════════════════════════════════════════════════════════════════
 */

namespace Ogan\Form\Types;

trait FieldTypeTrait
{
    /**
     * Rendre uniquement le label
     */
    public function renderLabel(string $name, array $options): string
    {
        $label = $options['label'] ?? ucfirst($name);
        $required = $options['required'] ?? false;

        $html = '<label for="' . htmlspecialchars($name) . '" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">';
        $html .= htmlspecialchars($label);
        if ($required) {
            $html .= ' <span class="text-red-500">*</span>';
        }
        $html .= '</label>';

        return $html;
    }

    /**
     * Rendre uniquement les erreurs
     */
    public function renderErrors(array $errors): string
    {
        if (empty($errors)) {
            return '';
        }

        $html = '<div class="mt-1">';
        foreach ($errors as $error) {
            $html .= '<p class="text-sm text-red-600 dark:text-red-400">' . htmlspecialchars($error) . '</p>';
        }
        $html .= '</div>';

        return $html;
    }
}
