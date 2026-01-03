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
     * 
     * Options supportées :
     * - label : Texte du label (défaut: nom du champ capitalisé)
     * - label_attr : Attributs HTML du label (class, id, etc.)
     * - required : Affiche une étoile rouge si true
     */
    public function renderLabel(string $name, array $options): string
    {
        $label = $options['label'] ?? ucfirst($name);
        $required = $options['required'] ?? false;
        $labelAttr = $options['label_attr'] ?? [];

        // Classes par défaut pour le label
        $defaultClass = 'block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2';

        // Fusionner avec les classes personnalisées si fournies
        if (isset($labelAttr['class'])) {
            $labelAttr['class'] = $defaultClass . ' ' . $labelAttr['class'];
        } else {
            $labelAttr['class'] = $defaultClass;
        }

        // Générer les attributs HTML
        $attrString = '';
        foreach ($labelAttr as $key => $value) {
            $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
        }

        $html = '<label for="' . htmlspecialchars($name) . '"' . $attrString . '>';
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
