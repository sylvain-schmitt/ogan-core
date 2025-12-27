<?php

/**
 * ═══════════════════════════════════════════════════════════════════════
 * 📝 TEXT HELPER - Manipulation de texte
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * Utilitaires pour la manipulation de chaînes de caractères.
 * 
 * Usage:
 *   $excerpt = Text::excerpt($article->getContent(), 150);
 *   $truncated = Text::truncate($title, 50);
 *   $words = Text::words($content, 20);
 * 
 * ═══════════════════════════════════════════════════════════════════════
 */

namespace Ogan\Util;

class Text
{
    /**
     * Génère un extrait de texte (pour les listes d'articles)
     * 
     * - Supprime les tags HTML
     * - Tronque à la longueur spécifiée
     * - Ajoute "..." si tronqué
     * - Respecte les mots entiers
     * 
     * @param string $text Texte à extraire
     * @param int $length Longueur maximale (défaut: 150)
     * @param string $suffix Suffixe si tronqué (défaut: ...)
     * @return string
     */
    public static function excerpt(string $text, int $length = 150, string $suffix = '...'): string
    {
        // Supprimer les tags HTML
        $text = strip_tags($text);

        // Normaliser les espaces
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        // Si le texte est assez court, le retourner tel quel
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        // Tronquer en respectant les mots entiers
        $excerpt = mb_substr($text, 0, $length);

        // Ne pas couper au milieu d'un mot
        $lastSpace = mb_strrpos($excerpt, ' ');
        if ($lastSpace !== false) {
            $excerpt = mb_substr($excerpt, 0, $lastSpace);
        }

        return rtrim($excerpt, '.,!?;:') . $suffix;
    }

    /**
     * Tronque un texte à une longueur donnée
     * 
     * @param string $text Texte à tronquer
     * @param int $length Longueur maximale
     * @param string $suffix Suffixe si tronqué
     * @return string
     */
    public static function truncate(string $text, int $length = 100, string $suffix = '...'): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length) . $suffix;
    }

    /**
     * Limite le texte à un nombre de mots
     * 
     * @param string $text Texte
     * @param int $words Nombre de mots maximum
     * @param string $suffix Suffixe si tronqué
     * @return string
     */
    public static function words(string $text, int $words = 20, string $suffix = '...'): string
    {
        // Supprimer les tags HTML
        $text = strip_tags($text);

        // Normaliser les espaces
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        // Séparer en mots
        $wordArray = explode(' ', $text);

        if (count($wordArray) <= $words) {
            return $text;
        }

        $limited = array_slice($wordArray, 0, $words);
        return implode(' ', $limited) . $suffix;
    }

    /**
     * Calcule le temps de lecture estimé
     * 
     * @param string $text Texte
     * @param int $wordsPerMinute Vitesse de lecture (défaut: 200)
     * @return int Minutes de lecture
     */
    public static function readingTime(string $text, int $wordsPerMinute = 200): int
    {
        $text = strip_tags($text);
        $wordCount = str_word_count($text);
        $minutes = ceil($wordCount / $wordsPerMinute);

        return max(1, (int)$minutes);
    }

    /**
     * Retourne le temps de lecture formaté
     * 
     * @param string $text Texte
     * @param int $wordsPerMinute Vitesse de lecture
     * @return string "X min de lecture"
     */
    public static function readingTimeFormatted(string $text, int $wordsPerMinute = 200): string
    {
        $minutes = self::readingTime($text, $wordsPerMinute);
        return $minutes . ' min de lecture';
    }

    /**
     * Compte les mots dans un texte
     * 
     * @param string $text Texte
     * @return int Nombre de mots
     */
    public static function wordCount(string $text): int
    {
        $text = strip_tags($text);
        return str_word_count($text);
    }

    /**
     * Supprime les tags HTML et normalise les espaces
     * 
     * @param string $text Texte HTML
     * @return string Texte brut
     */
    public static function stripHtml(string $text): string
    {
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
}
