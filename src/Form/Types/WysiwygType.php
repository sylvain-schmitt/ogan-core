<?php

/**
 * ═══════════════════════════════════════════════════════════════════════
 * 📝 WYSIWYG TYPE - Éditeur de texte riche
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * Génère un textarea avec intégration WYSIWYG (TinyMCE CDN par défaut).
 * 
 * Usage:
 *   ->add('content', WysiwygType::class, [
 *       'label' => 'Contenu',
 *       'attr' => ['rows' => 10]
 *   ])
 * 
 * Options:
 *   - editor: 'tinymce' (défaut) | 'basic' (sans JS)
 *   - toolbar: 'full' | 'simple' | 'minimal'
 *   - height: hauteur en pixels (défaut: 400)
 * 
 * ═══════════════════════════════════════════════════════════════════════
 */

namespace Ogan\Form\Types;

class WysiwygType implements FieldTypeInterface
{
    use FieldTypeTrait;

    /**
     * CDN TinyMCE (version gratuite sans API key)
     */
    private const TINYMCE_CDN = 'https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js';

    public function render(string $name, mixed $value, array $options, array $errors): string
    {
        $label = $options['label'] ?? ucfirst($name);
        $required = $options['required'] ?? false;
        $attr = $options['attr'] ?? [];
        $editor = $options['editor'] ?? 'tinymce';
        $toolbar = $options['toolbar'] ?? 'full';
        $height = $options['height'] ?? 400;

        // Classes par défaut
        $defaultClass = 'w-full border border-gray-300 rounded-lg';
        $inputClass = $attr['class'] ?? $defaultClass;
        $rows = $attr['rows'] ?? 10;

        $html = '<div class="mb-4">';
        $html .= '<label for="' . htmlspecialchars($name) . '" class="block text-sm font-medium text-gray-700 mb-2">' . htmlspecialchars($label);
        if ($required) {
            $html .= ' <span class="text-red-500">*</span>';
        }
        $html .= '</label>';

        // Textarea
        $html .= '<textarea';
        $html .= ' id="' . htmlspecialchars($name) . '"';
        $html .= ' name="' . htmlspecialchars($name) . '"';
        $html .= ' class="' . htmlspecialchars($inputClass) . ' wysiwyg-editor"';
        $html .= ' rows="' . (int)$rows . '"';
        if ($required) {
            $html .= ' required';
        }

        // Attributs HTML supplémentaires
        foreach ($attr as $key => $val) {
            if (!in_array($key, ['class', 'rows'])) {
                $html .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($val) . '"';
            }
        }

        $html .= '>' . htmlspecialchars((string)$value) . '</textarea>';

        // Intégration TinyMCE si demandé
        if ($editor === 'tinymce') {
            $html .= $this->getTinyMceScript($name, $toolbar, $height);
        }

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

        $defaultClass = 'w-full border border-gray-300 rounded-lg p-3';
        $inputClass = $attr['class'] ?? $defaultClass;
        $rows = $attr['rows'] ?? 10;

        $html = '<textarea';
        $html .= ' id="' . htmlspecialchars($name) . '"';
        $html .= ' name="' . htmlspecialchars($name) . '"';
        $html .= ' class="' . htmlspecialchars($inputClass) . '"';
        $html .= ' rows="' . (int)$rows . '"';
        if ($required) {
            $html .= ' required';
        }

        foreach ($attr as $key => $val) {
            if (!in_array($key, ['class', 'rows'])) {
                $html .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($val) . '"';
            }
        }

        $html .= '>' . htmlspecialchars((string)$value) . '</textarea>';

        return $html;
    }

    /**
     * Génère le script TinyMCE
     */
    private function getTinyMceScript(string $name, string $toolbar, int $height): string
    {
        $toolbarConfig = $this->getToolbarConfig($toolbar);

        return <<<HTML
<script src="{$this->getCdnUrl()}" referrerpolicy="origin"></script>
<script>
    tinymce.init({
        selector: '#{$name}',
        height: {$height},
        menubar: false,
        plugins: 'lists link image code table wordcount',
        toolbar: '{$toolbarConfig}',
        content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; font-size: 14px; }',
        branding: false,
        promotion: false,
        license_key: 'gpl'
    });
</script>
HTML;
    }

    /**
     * Retourne l'URL du CDN TinyMCE
     */
    private function getCdnUrl(): string
    {
        // Utiliser la version GPL qui ne nécessite pas d'API key
        return 'https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js';
    }

    /**
     * Retourne la configuration de la toolbar selon le preset
     */
    private function getToolbarConfig(string $toolbar): string
    {
        return match ($toolbar) {
            'minimal' => 'bold italic | link',
            'simple' => 'bold italic underline | bullist numlist | link',
            'full' => 'undo redo | blocks | bold italic underline strikethrough | bullist numlist | link image | code',
            default => 'bold italic | bullist numlist | link'
        };
    }
}
