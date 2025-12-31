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
     * Génère le script TinyMCE avec support HTMX, Dark Mode et Custom UI
     */
    private function getTinyMceScript(string $name, string $toolbar, int $height): string
    {
        $toolbarConfig = $this->getToolbarConfig($toolbar);
        $cdnUrl = $this->getCdnUrl();

        return <<<HTML
<script>
(function() {
    // 1. Détecter le mode sombre
    function isDarkMode() {
        return document.documentElement.classList.contains('dark');
    }

    // 2. Récupérer les couleurs du thème actuel via un élément dummy
    function getThemeColors() {
        const dummy = document.createElement('div');
        dummy.className = 'bg-card border-default text-muted text-foreground hidden';
        dummy.style.display = 'none'; 
        document.body.appendChild(dummy);
        
        const styles = window.getComputedStyle(dummy);
        const colors = {
            bg: styles.backgroundColor,
            border: styles.borderColor,
            textMuted: styles.color,
            textMain: styles.getPropertyValue('color') // text-foreground
        };
        document.body.removeChild(dummy);
        return colors;
    }

    // 3. Injecter les SURCHARGES CSS DYNAMIQUES pour l'UI de TinyMCE
    function injectTinyMceOverrides() {
        const styleId = 'tinymce-overrides';
        let style = document.getElementById(styleId);
        
        if (!style) {
            style = document.createElement('style');
            style.id = styleId;
            document.head.appendChild(style);
        }

        const colors = getThemeColors();

        // Surcharge uniquement pour le mode dark (ou si on voulait supporter des thèmes light custom)
        // Ici on force les variables pour html.dark
        style.textContent = `
            html.dark .tox-tinymce {
                border-color: \${colors.border} !important;
            }
            html.dark .tox-editor-header,
            html.dark .tox-toolbar__primary,
            html.dark .tox-toolbar-overlord,
            html.dark .tox-editor-container,
            html.dark .tox-statusbar {
                background-color: \${colors.bg} !important;
                border-color: \${colors.border} !important;
            }
            html.dark .tox-tbtn {
                color: \${colors.textMuted} !important;
            }
            html.dark .tox-tbtn svg {
                fill: \${colors.textMuted} !important;
            }
            html.dark .tox-tbtn:hover {
                background-color: rgba(255, 255, 255, 0.1) !important;
                color: #fff !important;
            }
            html.dark .tox-tbtn:hover svg {
                fill: #fff !important;
            }
            html.dark .tox-edit-area__iframe {
                background-color: \${colors.bg} !important;
            }
            html.dark .tox-promotion { display: none !important; }
            html.dark .tox-toolbar__group {
                border-color: \${colors.border} !important;
            }
        `;
    }

    // 4. Configuration de l'éditeur
    function getEditorConfig() {
        var isDark = isDarkMode();
        var colors = getThemeColors();
        
        // CSS pour le CONTENU de l'éditeur (iframe)
        var contentStyle = `
            @import url('/assets/css/app.css');
            body { 
                font-family: 'Nunito', sans-serif; 
                font-size: 16px; 
                line-height: 1.6;
                padding: 1rem;
                background-color: transparent;
            }
        `;
        
        if (isDark) {
            // Si mode sombre, on force les couleurs récupérées sur le body
            contentStyle += `
                body {
                    background-color: \${colors.bg} !important;
                    color: #e5e7eb !important; /* text-gray-200 par défaut si numuted trop sombre */
                }
                a { color: #60a5fa !important; } /* blue-400 */
            `;
        }

        return {
            selector: '#{$name}',
            height: {$height},
            menubar: false,
            plugins: 'lists link image code table wordcount',
            toolbar: '{$toolbarConfig}',
            
            skin: isDark ? 'oxide-dark' : 'oxide',
            content_css: isDark ? 'dark' : 'default',
            body_class: isDark ? 'dark' : '',
            
            content_style: contentStyle,
            
            branding: false,
            promotion: false,
            license_key: 'gpl',
            setup: function(editor) {
                editor.on('change', function() {
                    editor.save();
                });
                
                // Au chargement, on s'assure que le fond est bon
                editor.on('Init', function() {
                    if (isDark) {
                        editor.getBody().style.backgroundColor = colors.bg;
                        editor.getBody().style.color = '#e5e7eb';
                    }
                });
            }
        };
    }

    // Instance de l'éditeur
    var currentEditor = null;

    // Fonction d'initialisation
    function initEditor() {
        // Appliquer les styles UI avant d'init
        if (isDarkMode()) {
            injectTinyMceOverrides();
        }

        if (typeof tinymce !== 'undefined') {
            if (tinymce.get('{$name}')) {
                tinymce.get('{$name}').remove();
            }
            tinymce.init(getEditorConfig()).then(function(editors) {
                currentEditor = editors[0];
            });
        }
    }

    // Observer les changements de classe sur <html>
    var themeObserver = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.attributeName === 'class') {
                // Re-injecter les overrides (pour mettre à jour les couleurs si elles changent)
                injectTinyMceOverrides();
                setTimeout(initEditor, 100);
            }
        });
    });

    themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class']
    });

    // Chargement initiale
    // Injecter les styles tout de suite si besoin
    injectTinyMceOverrides();

    if (typeof tinymce === 'undefined') {
        var script = document.createElement('script');
        script.src = '{$cdnUrl}';
        script.referrerPolicy = 'origin';
        script.onload = function() {
            initEditor();
        };
        document.head.appendChild(script);
    } else {
        initEditor();
    }

    // Support HTMX
    if (typeof htmx !== 'undefined') {
        document.body.addEventListener('htmx:afterSwap', function(evt) {
            var editorElement = document.getElementById('{$name}');
            if (editorElement && typeof tinymce !== 'undefined') {
                setTimeout(initEditor, 50);
            }
        });
        
        document.body.addEventListener('htmx:beforeSwap', function(evt) {
             if (tinymce.get('{$name}')) {
                tinymce.get('{$name}').remove();
            }
        });
    }
})();
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
