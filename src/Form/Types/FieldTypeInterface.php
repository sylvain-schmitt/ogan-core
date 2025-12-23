<?php

/**
 * ═══════════════════════════════════════════════════════════════════════
 * 📝 FIELDTYPEINTERFACE - Interface pour les Types de Champs
 * ═══════════════════════════════════════════════════════════════════════
 */

namespace Ogan\Form\Types;

interface FieldTypeInterface
{
    /**
     * Rendre le champ HTML complet (label + input + erreurs)
     * 
     * @param string $name Nom du champ
     * @param mixed $value Valeur du champ
     * @param array $options Options du champ
     * @param array $errors Erreurs de validation
     * @return string HTML du champ
     */
    public function render(string $name, mixed $value, array $options, array $errors): string;

    /**
     * Rendre uniquement le widget (input sans label ni erreurs)
     * 
     * @param string $name Nom du champ
     * @param mixed $value Valeur du champ
     * @param array $options Options du champ
     * @return string HTML du widget
     */
    public function renderWidget(string $name, mixed $value, array $options): string;

    /**
     * Rendre uniquement le label
     * 
     * @param string $name Nom du champ
     * @param array $options Options du champ
     * @return string HTML du label
     */
    public function renderLabel(string $name, array $options): string;

    /**
     * Rendre uniquement les erreurs
     * 
     * @param array $errors Erreurs de validation
     * @return string HTML des erreurs
     */
    public function renderErrors(array $errors): string;
}

