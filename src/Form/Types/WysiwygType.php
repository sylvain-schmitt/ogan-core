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
     * Génère le script TinyMCE avec support HTMX et Dark Mode
     */
    private function getTinyMceScript(string $name, string $toolbar, int $height): string
    {
        $toolbarConfig = $this->getToolbarConfig($toolbar);
        $cdnUrl = $this->getCdnUrl();

        return <<<HTML
<script>
(function() {
    // Détecter le mode sombre initial
    function isDarkMode() {
        return document.documentElement.classList.contains('dark');
    }

    // Configuration de base
    function getEditorConfig() {
        var isDark = isDarkMode();
        return {
            selector: '#{$name}',
            height: {$height},
            menubar: false,
            plugins: 'lists link image code table wordcount',
            toolbar: '{$toolbarConfig}',
            // Skin et CSS selon le thème
            skin: isDark ? 'oxide-dark' : 'oxide',
            content_css: isDark ? 'dark' : 'default',
            // Injecter le CSS de l'application pour que le contenu ressemble au site
            content_style: `
                @import url('/assets/css/app.css');
                body { 
                    font-family: 'Nunito', sans-serif; 
                    font-size: 16px; 
                    line-height: 1.6;
                    padding: 1rem;
                }
            `,
            branding: false,
            promotion: false,
            license_key: 'gpl',
            setup: function(editor) {
                // Synchroniser le contenu avant soumission du formulaire
                editor.on('change', function() {
                    editor.save();
                });
            }
        };
    }

    // Instance de l'éditeur
    var currentEditor = null;

    // Fonction d'initialisation
    function initEditor() {
        // Supprimer l'ancien éditeur s'il existe
        if (typeof tinymce !== 'undefined') {
            if (tinymce.get('{$name}')) {
                tinymce.get('{$name}').remove();
            }
            
            // Initialiser avec la config courante (skin clair/sombre)
            tinymce.init(getEditorConfig()).then(function(editors) {
                currentEditor = editors[0];
            });
        }
    }

    // Observer les changements de classe sur <html> pour le dark mode
    var themeObserver = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.attributeName === 'class') {
                // Si le thème a changé, on recharge l'éditeur
                // On attend un peu que le DOM soit à jour
                setTimeout(initEditor, 100);
            }
        });
    });

    // Démarrer l'observation du thème
    themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class']
    });

    // Charger TinyMCE si pas encore chargé
    if (typeof tinymce === 'undefined') {
        var script = document.createElement('script');
        script.src = '{$cdnUrl}';
        script.referrerPolicy = 'origin';
        script.onload = function() {
            initEditor();
        };
        document.head.appendChild(script);
    } else {
        // TinyMCE déjà chargé, initialiser directement
        initEditor();
    }

    // Réinitialiser après swap HTMX (si HTMX est présent)
    if (typeof htmx !== 'undefined') {
        document.body.addEventListener('htmx:afterSwap', function(evt) {
            // Vérifier si le nouveau contenu contient notre éditeur
            var editorElement = document.getElementById('{$name}');
            if (editorElement && typeof tinymce !== 'undefined') {
                // Petit délai pour s'assurer que le DOM est prêt
                setTimeout(initEditor, 50);
            }
        });
        
        // Nettoyage lors de la suppression de l'élément (pour éviter les fuites mémoire)
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
