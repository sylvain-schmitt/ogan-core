<?php

/**
 * ═══════════════════════════════════════════════════════════════════════
 * 👁️ FORMVIEW - Vue d'un Formulaire (pour le Rendu)
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * RÔLE :
 * ------
 * Représente un formulaire pour le rendu dans les vues.
 * Permet d'accéder aux champs et de les rendre.
 * 
 * ═══════════════════════════════════════════════════════════════════════
 */

namespace Ogan\Form;

use Ogan\View\SafeHtml;

class FormView
{
    /**
     * @var FormBuilder FormBuilder associé
     */
    private FormBuilder $formBuilder;

    /**
     * ═══════════════════════════════════════════════════════════════════
     * CONSTRUCTEUR
     * ═══════════════════════════════════════════════════════════════════
     */
    public function __construct(FormBuilder $formBuilder)
    {
        $this->formBuilder = $formBuilder;
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * ACCÉDER À UN CHAMP (ArrayAccess)
     * ═══════════════════════════════════════════════════════════════════
     * 
     * Permet d'utiliser $form['name'] pour accéder à un champ
     * 
     * @param string $name Nom du champ
     * @return FieldView Vue du champ
     * 
     * ═══════════════════════════════════════════════════════════════════
     */
    public function __get(string $name): FieldView
    {
        return new FieldView($name, $this->formBuilder);
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * RENDRE LE FORMULAIRE COMPLET
     * ═══════════════════════════════════════════════════════════════════
     * 
     * @return string HTML du formulaire
     * 
     * ═══════════════════════════════════════════════════════════════════
     */
    public function render(): string
    {
        $options = $this->formBuilder->getOptions();
        $method = $options['method'] ?? 'POST';
        $action = $options['action'] ?? '';
        $attr = $options['attr'] ?? [];

        // Vérifier si le formulaire contient un FileType
        $hasFileType = $this->hasFileType();

        $html = '<form method="' . htmlspecialchars($method) . '"';
        if ($action) {
            $html .= ' action="' . htmlspecialchars($action) . '"';
        }

        // Ajouter enctype="multipart/form-data" si nécessaire
        if ($hasFileType && !isset($attr['enctype'])) {
            $html .= ' enctype="multipart/form-data"';
        }

        // Attributs HTML
        foreach ($attr as $key => $value) {
            $html .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
        }

        $html .= '>';

        // Rendre tous les champs
        foreach ($this->formBuilder->getFields() as $name => $field) {
            $fieldView = new FieldView($name, $this->formBuilder);
            $html .= $fieldView->render();
        }

        $html .= '</form>';

        return $html;
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * RENDRE LE FORMULAIRE (magic method)
     * ═══════════════════════════════════════════════════════════════════
     */
    public function __toString(): string
    {
        return $this->render();
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * RÉCUPÉRER LES ERREURS
     * ═══════════════════════════════════════════════════════════════════
     */
    public function getErrors(): array
    {
        return $this->formBuilder->getErrors();
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * VÉRIFIER SI LE FORMULAIRE CONTIENT UN FILETYPE
     * ═══════════════════════════════════════════════════════════════════
     * 
     * @return bool
     * 
     * ═══════════════════════════════════════════════════════════════════
     */
    private function hasFileType(): bool
    {
        $fields = $this->formBuilder->getFields();
        foreach ($fields as $field) {
            if ($field['type'] === \Ogan\Form\Types\FileType::class) {
                return true;
            }
        }
        return false;
    }
}

/**
 * ═══════════════════════════════════════════════════════════════════════
 * 👁️ FIELDVIEW - Vue d'un Champ de Formulaire
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * Permet de rendre un champ de formulaire de manière granulaire :
 * - {{ form.email }}        → Champ complet (label + widget + erreurs)
 * - {{ form.email.label }}  → Juste le label
 * - {{ form.email.widget }} → Juste l'input
 * - {{ form.email.errors }} → Juste les erreurs
 * - {{ form.email.row }}    → Alias de render() (champ complet)
 * 
 * ═══════════════════════════════════════════════════════════════════════
 */
class FieldView
{
    private string $name;
    private FormBuilder $formBuilder;

    public function __construct(string $name, FormBuilder $formBuilder)
    {
        $this->name = $name;
        $this->formBuilder = $formBuilder;
    }

    /**
     * Accès magique aux sous-éléments (label, widget, errors, row)
     */
    public function __get(string $property): SafeHtml|string
    {
        $html = match ($property) {
            'label' => $this->label(),
            'widget' => $this->widget(),
            'errors' => $this->errors(),
            'row' => $this->render(),
            default => '',
        };

        // Wrapper dans SafeHtml pour éviter l'échappement
        return $html !== '' ? new SafeHtml($html) : '';
    }

    /**
     * Rendre le champ complet (label + widget + erreurs)
     */
    public function render(): string
    {
        $fields = $this->formBuilder->getFields();
        $field = $fields[$this->name] ?? null;

        if (!$field) {
            return '';
        }

        $type = $field['type'];
        $options = $field['options'];
        $data = $this->formBuilder->getData();
        $value = $data[$this->name] ?? $options['data'] ?? '';
        $errors = $this->formBuilder->getErrors();
        $fieldErrors = $errors[$this->name] ?? [];

        // Instancier le type et rendre le champ complet
        $typeInstance = new $type();
        return $typeInstance->render($this->name, $value, $options, $fieldErrors);
    }

    /**
     * Rendre uniquement le label
     */
    public function label(): string
    {
        $fields = $this->formBuilder->getFields();
        $field = $fields[$this->name] ?? null;

        if (!$field) {
            return '';
        }

        $typeInstance = new $field['type']();
        return $typeInstance->renderLabel($this->name, $field['options']);
    }

    /**
     * Rendre uniquement le widget (input)
     */
    public function widget(): string
    {
        $fields = $this->formBuilder->getFields();
        $field = $fields[$this->name] ?? null;

        if (!$field) {
            return '';
        }

        $data = $this->formBuilder->getData();
        $value = $data[$this->name] ?? $field['options']['data'] ?? '';

        $typeInstance = new $field['type']();
        return $typeInstance->renderWidget($this->name, $value, $field['options']);
    }

    /**
     * Rendre uniquement les erreurs
     */
    public function errors(): string
    {
        $errors = $this->formBuilder->getErrors();
        $fieldErrors = $errors[$this->name] ?? [];

        if (empty($fieldErrors)) {
            return '';
        }

        $fields = $this->formBuilder->getFields();
        $field = $fields[$this->name] ?? null;

        if (!$field) {
            // Fallback si pas de type trouvé
            $html = '<div class="mt-1">';
            foreach ($fieldErrors as $error) {
                $html .= '<p class="text-sm text-red-600">' . htmlspecialchars($error) . '</p>';
            }
            $html .= '</div>';
            return $html;
        }

        $typeInstance = new $field['type']();
        return $typeInstance->renderErrors($fieldErrors);
    }

    /**
     * Magic method pour le rendu ({{ form.fieldName }})
     */
    public function __toString(): string
    {
        return $this->render();
    }
}
