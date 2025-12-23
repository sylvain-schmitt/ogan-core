<?php

/**
 * ═══════════════════════════════════════════════════════════════════════
 * 🔒 SAFEHTML - Wrapper pour HTML sûr (non échappé)
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * Cette classe wrappe une chaîne HTML qui ne doit PAS être échappée
 * par le moteur de template. Utilisée par les formulaires et composants
 * qui génèrent du HTML sûr.
 * 
 * ═══════════════════════════════════════════════════════════════════════
 */

namespace Ogan\View;

class SafeHtml implements \Stringable
{
    private string $html;

    public function __construct(string $html)
    {
        $this->html = $html;
    }

    public function __toString(): string
    {
        return $this->html;
    }

    /**
     * Crée une instance SafeHtml à partir d'une chaîne
     */
    public static function wrap(string $html): self
    {
        return new self($html);
    }
}
