<?php

namespace Ogan\Form\Types;

class EmailType implements FieldTypeInterface
{
    use FieldTypeTrait;

    public function render(string $name, mixed $value, array $options, array $errors): string
    {
        $html = '<div class="mb-4">';

        // Utiliser renderLabel du trait (supporte label_attr)
        $html .= $this->renderLabel($name, $options);

        // Rendre le widget
        $html .= $this->renderWidget($name, $value, $options);

        // Utiliser renderErrors du trait
        $html .= $this->renderErrors($errors);

        $html .= '</div>';

        return $html;
    }

    public function renderWidget(string $name, mixed $value, array $options): string
    {
        $required = $options['required'] ?? false;
        $attr = $options['attr'] ?? [];
        $defaultClass = 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent';
        $inputClass = $attr['class'] ?? $defaultClass;

        $html = '<input type="email"';
        $html .= ' id="' . htmlspecialchars($name) . '"';
        $html .= ' name="' . htmlspecialchars($name) . '"';
        $html .= ' value="' . htmlspecialchars((string)$value) . '"';
        $html .= ' class="' . htmlspecialchars($inputClass) . '"';
        if ($required) {
            $html .= ' required';
        }
        foreach ($attr as $key => $val) {
            if ($key !== 'class') {
                $html .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($val) . '"';
            }
        }
        $html .= '>';
        return $html;
    }
}
