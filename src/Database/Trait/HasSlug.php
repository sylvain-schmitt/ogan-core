<?php

/**
 * ═══════════════════════════════════════════════════════════════════════
 * 🔗 HAS SLUG TRAIT - Génération automatique de slugs pour les modèles
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * Ajoute la génération automatique de slugs uniques aux modèles.
 * 
 * Usage:
 *   class Article extends Model
 *   {
 *       use HasSlug;
 *       
 *       // Optionnel: personnaliser le champ source
 *       protected string $slugSource = 'title';  // Par défaut: 'title'
 *       protected string $slugField = 'slug';     // Par défaut: 'slug'
 *   }
 * 
 * Le slug sera généré automatiquement lors de la sauvegarde si:
 *   - Le champ slug est vide
 *   - Le champ source a changé
 * 
 * ═══════════════════════════════════════════════════════════════════════
 */

namespace Ogan\Database\Trait;

use Ogan\Util\Slugger;

trait HasSlug
{
    /**
     * Initialise le trait (appelé par le Model)
     */
    protected function initializeHasSlug(): void
    {
        // Enregistrer le hook de sauvegarde
        if (method_exists($this, 'registerSaveHook')) {
            $this->registerSaveHook('generateSlugIfNeeded');
        }
    }

    /**
     * Génère le slug si nécessaire avant la sauvegarde
     */
    protected function generateSlugIfNeeded(): void
    {
        $slugField = $this->getSlugField();
        $slugSource = $this->getSlugSource();

        // Récupérer les valeurs actuelles
        $currentSlug = $this->getAttribute($slugField);
        $sourceValue = $this->getAttribute($slugSource);

        // Ne pas générer si pas de source
        if (empty($sourceValue)) {
            return;
        }

        // Générer le slug si vide ou si la source a changé
        if (empty($currentSlug) || $this->hasSourceChanged()) {
            $this->generateUniqueSlug();
        }
    }

    /**
     * Génère un slug unique pour ce modèle
     */
    public function generateUniqueSlug(): static
    {
        $slugField = $this->getSlugField();
        $slugSource = $this->getSlugSource();
        $sourceValue = $this->getAttribute($slugSource);

        if (empty($sourceValue)) {
            return $this;
        }

        $slug = Slugger::unique(
            $sourceValue,
            static::class,
            $slugField,
            $this->getId() ?? null
        );

        $this->setAttribute($slugField, $slug);

        return $this;
    }

    /**
     * Force la régénération du slug
     */
    public function regenerateSlug(): static
    {
        return $this->generateUniqueSlug();
    }

    /**
     * Retourne le nom du champ slug
     */
    protected function getSlugField(): string
    {
        return $this->slugField ?? 'slug';
    }

    /**
     * Retourne le nom du champ source pour le slug
     */
    protected function getSlugSource(): string
    {
        return $this->slugSource ?? 'title';
    }

    /**
     * Vérifie si le champ source a changé
     */
    protected function hasSourceChanged(): bool
    {
        if (!method_exists($this, 'isDirty')) {
            return false;
        }

        return $this->isDirty($this->getSlugSource());
    }

    /**
     * Trouve un enregistrement par son slug
     * 
     * @param string $slug
     * @return static|null
     */
    public static function findBySlug(string $slug): ?static
    {
        $instance = new static();
        $slugField = $instance->getSlugField();

        if (method_exists(static::class, 'where')) {
            $query = static::where($slugField, '=', $slug);

            if (is_object($query) && method_exists($query, 'first')) {
                return $query->first();
            }

            return is_array($query) ? (reset($query) ?: null) : null;
        }

        return null;
    }

    /**
     * Trouve un enregistrement par son slug ou lance une exception
     * 
     * @param string $slug
     * @return static
     * @throws \Exception
     */
    public static function findBySlugOrFail(string $slug): static
    {
        $result = static::findBySlug($slug);

        if ($result === null) {
            throw new \Exception("Model not found with slug: {$slug}");
        }

        return $result;
    }
}
