<?php

/**
 * ═══════════════════════════════════════════════════════════════════════
 * 🎨 COLOR TYPE - Champ sélecteur de couleur
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * Génère un input type="color" pour sélectionner une couleur.
 * 
 * Usage:
 *   ->add('color', ColorType::class, [
 *       'label' => 'Couleur',
 *       'attr' => ['value' => '#C07459']
 *   ])
 * 
 * ═══════════════════════════════════════════════════════════════════════
 */

namespace Ogan\Form\Types;

class ColorType implements FieldTypeInterface
{
    use FieldTypeTrait;

    public function render(string $name, mixed $value, array $options, array $errors): string
    {
        $label = $options['label'] ?? ucfirst($name);
        $required = $options['required'] ?? false;
        $attr = $options['attr'] ?? [];

        // Valeur par défaut
        $defaultValue = $attr['value'] ?? '#000000';
        $value = $value ?: $defaultValue;

        // Classes par défaut Tailwind
        $defaultClass = 'w-16 h-10 border border-gray-300 rounded-lg cursor-pointer p-1';
        $inputClass = $attr['class'] ?? $defaultClass;

        $html = '<div class="mb-4">';
        $html .= '<label for="' . htmlspecialchars($name) . '" class="block text-sm font-medium text-gray-700 mb-2">' . htmlspecialchars($label);
        if ($required) {
            $html .= ' <span class="text-red-500">*</span>';
        }
        $html .= '</label>';

        $html .= '<div class="flex items-center gap-3">';
        $html .= '<input type="color"';
        $html .= ' id="' . htmlspecialchars($name) . '"';
        $html .= ' name="' . htmlspecialchars($name) . '"';
        $html .= ' value="' . htmlspecialchars((string)$value) . '"';
        $html .= ' class="' . htmlspecialchars($inputClass) . '"';
        if ($required) {
            $html .= ' required';
        }

        // Attributs HTML (sauf class et value qui sont déjà gérés)
        foreach ($attr as $key => $val) {
            if ($key !== 'class' && $key !== 'value') {
                $html .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($val) . '"';
            }
        }

        $html .= '>';

        // Afficher la valeur hex à côté
        $html .= '<span class="text-sm text-gray-500" id="' . htmlspecialchars($name) . '_preview">' . htmlspecialchars((string)$value) . '</span>';
        $html .= '</div>';

        // Script pour mettre à jour la preview
        $html .= '<script>
            document.getElementById("' . htmlspecialchars($name) . '").addEventListener("input", function(e) {
                document.getElementById("' . htmlspecialchars($name) . '_preview").textContent = e.target.value;
            });
        </script>';

        // Afficher les erreurs
        if (!empty($errors)) {
            $html .= '<div class="mt-1">';
            foreach ($errors as $error) {
                $html .= '<p class="text-sm text-red-600">' . htmlspecialchars($error) . '</p>';
            }
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    public function renderWidget(string $name, mixed $value, array $options): string
    {
        $required = $options['required'] ?? false;
        $attr = $options['attr'] ?? [];

        $defaultValue = $attr['value'] ?? '#000000';
        $value = $value ?: $defaultValue;

        $defaultClass = 'w-16 h-10 border border-gray-300 rounded-lg cursor-pointer p-1';
        $inputClass = $attr['class'] ?? $defaultClass;

        $html = '<input type="color"';
        $html .= ' id="' . htmlspecialchars($name) . '"';
        $html .= ' name="' . htmlspecialchars($name) . '"';
        $html .= ' value="' . htmlspecialchars((string)$value) . '"';
        $html .= ' class="' . htmlspecialchars($inputClass) . '"';
        if ($required) {
            $html .= ' required';
        }

        foreach ($attr as $key => $val) {
            if ($key !== 'class' && $key !== 'value') {
                $html .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($val) . '"';
            }
        }

        $html .= '>';

        return $html;
    }
}
