<?php

/**
 * ═══════════════════════════════════════════════════════════════════════
 * 🔗 SLUGGER - Génération de slugs URL-friendly
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * Convertit du texte en slugs propres pour les URLs.
 * Supporte les accents, les caractères spéciaux et l'unicité en BDD.
 * 
 * Usage:
 *   $slug = Slugger::slugify('Mon Article de Blog');
 *   // → "mon-article-de-blog"
 *   
 *   $slug = Slugger::unique('Mon Article', Article::class, 'slug');
 *   // → "mon-article" ou "mon-article-2" si déjà pris
 * 
 * ═══════════════════════════════════════════════════════════════════════
 */

namespace Ogan\Util;

class Slugger
{
    /**
     * Table de translitération pour les caractères accentués
     */
    private static array $transliterationTable = [
        'À' => 'A',
        'Á' => 'A',
        'Â' => 'A',
        'Ã' => 'A',
        'Ä' => 'A',
        'Å' => 'A',
        'Æ' => 'AE',
        'Ç' => 'C',
        'È' => 'E',
        'É' => 'E',
        'Ê' => 'E',
        'Ë' => 'E',
        'Ì' => 'I',
        'Í' => 'I',
        'Î' => 'I',
        'Ï' => 'I',
        'Ð' => 'D',
        'Ñ' => 'N',
        'Ò' => 'O',
        'Ó' => 'O',
        'Ô' => 'O',
        'Õ' => 'O',
        'Ö' => 'O',
        'Ø' => 'O',
        'Ù' => 'U',
        'Ú' => 'U',
        'Û' => 'U',
        'Ü' => 'U',
        'Ý' => 'Y',
        'Þ' => 'TH',
        'ß' => 'ss',
        'à' => 'a',
        'á' => 'a',
        'â' => 'a',
        'ã' => 'a',
        'ä' => 'a',
        'å' => 'a',
        'æ' => 'ae',
        'ç' => 'c',
        'è' => 'e',
        'é' => 'e',
        'ê' => 'e',
        'ë' => 'e',
        'ì' => 'i',
        'í' => 'i',
        'î' => 'i',
        'ï' => 'i',
        'ð' => 'd',
        'ñ' => 'n',
        'ò' => 'o',
        'ó' => 'o',
        'ô' => 'o',
        'õ' => 'o',
        'ö' => 'o',
        'ø' => 'o',
        'ù' => 'u',
        'ú' => 'u',
        'û' => 'u',
        'ü' => 'u',
        'ý' => 'y',
        'þ' => 'th',
        'ÿ' => 'y',
        'Œ' => 'OE',
        'œ' => 'oe',
        'Š' => 'S',
        'š' => 's',
        'Ž' => 'Z',
        'ž' => 'z',
        'ƒ' => 'f',
    ];

    /**
     * Convertit un texte en slug URL-friendly
     * 
     * @param string $text Texte à convertir
     * @param string $separator Séparateur (par défaut: -)
     * @param int $maxLength Longueur maximale (0 = pas de limite)
     * @return string
     */
    public static function slugify(string $text, string $separator = '-', int $maxLength = 0): string
    {
        // Convertir en minuscules
        $text = mb_strtolower($text, 'UTF-8');

        // Remplacer les caractères accentués
        $text = strtr($text, self::$transliterationTable);

        // Si transliterator est disponible (intl), l'utiliser en complément
        if (function_exists('transliterator_transliterate')) {
            $text = transliterator_transliterate('Any-Latin; Latin-ASCII', $text) ?: $text;
        }

        // Remplacer les caractères non alphanumériques par le séparateur
        $text = preg_replace('/[^a-z0-9]+/', $separator, $text);

        // Supprimer les séparateurs en début et fin
        $text = trim($text, $separator);

        // Supprimer les séparateurs multiples
        $text = preg_replace('/' . preg_quote($separator, '/') . '+/', $separator, $text);

        // Limiter la longueur si spécifié
        if ($maxLength > 0 && mb_strlen($text) > $maxLength) {
            $text = mb_substr($text, 0, $maxLength);
            // Ne pas couper au milieu d'un mot
            $text = rtrim($text, $separator);
        }

        return $text;
    }

    /**
     * Génère un slug unique en vérifiant la base de données
     * 
     * @param string $text Texte à convertir en slug
     * @param string $modelClass Classe du modèle (ex: Article::class)
     * @param string $slugField Nom du champ slug (par défaut: 'slug')
     * @param int|null $excludeId ID à exclure (pour les mises à jour)
     * @param string $separator Séparateur (par défaut: -)
     * @return string
     */
    public static function unique(
        string $text,
        string $modelClass,
        string $slugField = 'slug',
        ?int $excludeId = null,
        string $separator = '-'
    ): string {
        $baseSlug = self::slugify($text, $separator);
        $slug = $baseSlug;
        $counter = 1;

        while (self::slugExists($slug, $modelClass, $slugField, $excludeId)) {
            $counter++;
            $slug = $baseSlug . $separator . $counter;
        }

        return $slug;
    }

    /**
     * Vérifie si un slug existe déjà en base de données
     * 
     * @param string $slug Slug à vérifier
     * @param string $modelClass Classe du modèle
     * @param string $slugField Nom du champ slug
     * @param int|null $excludeId ID à exclure
     * @return bool
     */
    private static function slugExists(
        string $slug,
        string $modelClass,
        string $slugField,
        ?int $excludeId
    ): bool {
        if (!class_exists($modelClass)) {
            return false;
        }

        // Essayer avec findBySlug si disponible
        $finderMethod = 'findBy' . ucfirst($slugField);
        if (method_exists($modelClass, $finderMethod)) {
            $existing = call_user_func([$modelClass, $finderMethod], $slug);
            if ($existing) {
                if ($excludeId !== null && method_exists($existing, 'getId') && $existing->getId() === $excludeId) {
                    return false;
                }
                return true;
            }
            return false;
        }

        // Fallback: utiliser where() si disponible
        if (method_exists($modelClass, 'where')) {
            $query = call_user_func([$modelClass, 'where'], $slugField, '=', $slug);

            if (is_object($query) && method_exists($query, 'first')) {
                $existing = $query->first();
            } else {
                $existing = is_array($query) ? reset($query) : $query;
            }

            if ($existing) {
                if ($excludeId !== null && method_exists($existing, 'getId') && $existing->getId() === $excludeId) {
                    return false;
                }
                return true;
            }
        }

        return false;
    }

    /**
     * Génère un slug à partir d'un tableau de textes
     * Utile pour les slugs composés (ex: catégorie + titre)
     * 
     * @param array $parts Parties du slug
     * @param string $separator Séparateur
     * @return string
     */
    public static function fromParts(array $parts, string $separator = '-'): string
    {
        $slugParts = array_map(fn($part) => self::slugify($part, $separator), $parts);
        return implode($separator, array_filter($slugParts));
    }
}
