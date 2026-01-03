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
        $attr = $options['attr'] ?? [];

        // Valeur par défaut
        $defaultValue = $attr['value'] ?? '#000000';
        $value = $value ?: $defaultValue;

        // Classes par défaut Tailwind
        $defaultClass = 'w-16 h-10 border border-gray-300 rounded-lg cursor-pointer p-1';
        $inputClass = $attr['class'] ?? $defaultClass;
        $required = $options['required'] ?? false;

        $html = '<div class="mb-4">';

        // Utiliser renderLabel du trait (supporte label_attr)
        $html .= $this->renderLabel($name, $options);

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

        // Utiliser renderErrors du trait
        $html .= $this->renderErrors($errors);

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
