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

    // 2. Injecter les SURCHARGES CSS DYNAMIQUES pour l'UI de TinyMCE
    // Cette fonction récupère les couleurs réelles du thème (bg-card, border-default...)
    // en créant un élément invisible, pour que TinyMCE s'adapte à N'IMPORTE QUEL thème Ogan.
    function injectTinyMceOverrides() {
        const styleId = 'tinymce-overrides';
        let style = document.getElementById(styleId);
        
        if (!style) {
            style = document.createElement('style');
            style.id = styleId;
            document.head.appendChild(style);
        }

        // Créer un élément témoin pour lire les couleurs calculées par le navigateur
        const dummy = document.createElement('div');
        // On lui donne les classes du framework dont on veut récupérer les couleurs
        // bg-card : fond des panneaux
        // border-default : couleur des bordures
        // text-muted : couleur du texte secondaire
        // text-foreground : couleur du texte principal
        dummy.className = 'bg-card border-default text-muted text-foreground hidden';
        dummy.style.display = 'none'; 
        document.body.appendChild(dummy);
        
        const styles = window.getComputedStyle(dummy);
        
        // Récupération des couleurs calculées (rgb ou hex)
        const bgCard = styles.backgroundColor;
        const borderColor = styles.borderColor;
        const textMuted = styles.color; 
        
        // Pour le hover, on peut essayer d'assombrir ou éclaircir, ou juste utiliser une valeur sémantique si dispo.
        // Ici on va utiliser une astuce de transparence pour le hover
        
        document.body.removeChild(dummy);

        // On n'applique ces surcharges QUE si on est en mode dark 
        // (car le mode light de TinyMCE 'oxide' est généralement très bien et standard)
        // Mais l'utilisateur peut vouloir que ça matche son thème light aussi.
        // Pour l'instant on cible html.dark pour répondre à la demande spécifique.
        
        style.textContent = `
            /* Mode Sombre Dynamique */
            html.dark .tox-tinymce {
                border-color: \${borderColor} !important;
            }
            html.dark .tox-editor-header,
            html.dark .tox-toolbar__primary,
            html.dark .tox-toolbar-overlord,
            html.dark .tox-editor-container,
            html.dark .tox-statusbar {
                background-color: \${bgCard} !important;
                border-color: \${borderColor} !important;
            }
            /* Boutons de la toolbar */
            html.dark .tox-tbtn {
                color: \${textMuted} !important;
            }
            html.dark .tox-tbtn svg {
                fill: \${textMuted} !important;
            }
            html.dark .tox-tbtn:hover {
                background-color: rgba(255, 255, 255, 0.1) !important;
                color: #fff !important;
            }
            html.dark .tox-tbtn:hover svg {
                fill: #fff !important;
            }
            
            /* Zone d'édition (iframe container) */
            html.dark .tox-edit-area__iframe {
                background-color: transparent !important; /* Laisser voir le fond du body de l'iframe */
            }
            
            /* Masquer la promo 'Upgrade' */
            html.dark .tox-promotion { display: none !important; }
            
            /* Séparateurs */
            html.dark .tox-toolbar__group {
                border-color: \${borderColor} !important;
            }
        `;
    }
    
    // Injecter les styles tout de suite
    injectTinyMceOverrides();

    // 3. Configuration de l'éditeur
    function getEditorConfig() {
        var isDark = isDarkMode();
        return {
            selector: '#{$name}',
            height: {$height},
            menubar: false,
            plugins: 'lists link image code table wordcount',
            toolbar: '{$toolbarConfig}',
            
            // On utilise le skin 'oxide-dark' si sombre, mais nos surcharges CSS (au-dessus) 
            // vont affiner les couleurs pour coller au site.
            skin: isDark ? 'oxide-dark' : 'oxide',
            
            // Important : charger le CSS content 'dark' si mode sombre
            content_css: isDark ? 'dark' : 'default',
            
            // Classe ajoutée au body de l'iframe
            body_class: isDark ? 'dark' : '',
            
            // CSS injecté DANS l'iframe (pour le contenu)
            content_style: `
                @import url('/assets/css/app.css');
                body { 
                    font-family: 'Nunito', sans-serif; 
                    font-size: 16px; 
                    line-height: 1.6;
                    padding: 1rem;
                    /* Forcer le fond transparent pour prendre la couleur de l'iframe définie par nos overrides */
                    background-color: transparent !important; 
                }
                /* Si le mode dark n'est pas détecté par app.css dans l'iframe, on force les couleurs */
                body.dark {
                    color: #e5e7eb; /* text-gray-200 */
                }
            `,
            branding: false,
            promotion: false,
            license_key: 'gpl',
            setup: function(editor) {
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
        if (typeof tinymce !== 'undefined') {
            if (tinymce.get('{$name}')) {
                tinymce.get('{$name}').remove();
            }
            tinymce.init(getEditorConfig()).then(function(editors) {
                currentEditor = editors[0];
            });
        }
    }

    // Observer les changements de classe sur <html> pour le changement dynamique
    var themeObserver = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.attributeName === 'class') {
                setTimeout(initEditor, 100);
            }
        });
    });

    themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class']
    });

    // Chargement du script
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
