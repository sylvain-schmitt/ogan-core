<?php

/**
 * ═══════════════════════════════════════════════════════════════════════
 * 🔐 SECURITYHELPER - Helpers de sécurité pour les vues
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * RÔLE :
 * ------
 * Fournit des helpers pour la protection CSRF dans les formulaires.
 * 
 * ═══════════════════════════════════════════════════════════════════════
 */

namespace Ogan\View\Helper;

use Ogan\Security\CsrfTokenManager;

class SecurityHelper
{
    private ?CsrfTokenManager $csrfTokenManager = null;

    public function setCsrfTokenManager(CsrfTokenManager $manager): void
    {
        $this->csrfTokenManager = $manager;
    }

    /**
     * Génère un token CSRF
     * 
     * @param string $tokenId L'identifiant du token (par défaut 'form')
     */
    public function csrfToken(string $tokenId = 'form'): string
    {
        if (!$this->csrfTokenManager) {
            return '';
        }
        return $this->csrfTokenManager->getToken($tokenId);
    }

    /**
     * Génère un champ hidden avec le token CSRF
     * 
     * @param string $tokenId L'identifiant du token
     */
    public function csrfInput(string $tokenId = 'form'): string
    {
        $token = $this->csrfToken($tokenId);
        return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
    }
}
