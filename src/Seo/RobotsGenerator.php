<?php

/**
 * ═══════════════════════════════════════════════════════════════════════
 * 🤖 ROBOTS GENERATOR - Génération de robots.txt pour SEO
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * RÔLE :
 * ------
 * Génère un fichier robots.txt pour contrôler l'accès des crawlers.
 * Permet de bloquer certaines sections du site et indiquer le sitemap.
 * 
 * EXEMPLES D'UTILISATION :
 * ------------------------
 * 
 * $robots = new RobotsGenerator('https://example.com');
 * $robots->allow('/')
 *        ->disallow('/admin/')
 *        ->disallow('/api/')
 *        ->sitemap('/sitemap.xml')
 *        ->save();
 * 
 * ═══════════════════════════════════════════════════════════════════════
 */

namespace Ogan\Seo;

class RobotsGenerator
{
    /**
     * @var string Base URL du site
     */
    private string $baseUrl;

    /**
     * @var array Règles par user-agent
     */
    private array $rules = [];

    /**
     * @var string User-agent courant
     */
    private string $currentUserAgent = '*';

    /**
     * @var array URLs de sitemaps
     */
    private array $sitemaps = [];

    /**
     * @var int|null Délai de crawl en secondes
     */
    private ?int $crawlDelay = null;

    /**
     * ═══════════════════════════════════════════════════════════════════
     * CONSTRUCTEUR
     * ═══════════════════════════════════════════════════════════════════
     * 
     * Initialise avec des règles par défaut (block /admin/ et /api/).
     * 
     * @param string $baseUrl URL de base du site (ex: https://example.com)
     * @param bool $withDefaults Ajouter les règles par défaut
     * 
     * ═══════════════════════════════════════════════════════════════════
     */
    public function __construct(string $baseUrl, bool $withDefaults = true)
    {
        $this->baseUrl = rtrim($baseUrl, '/');

        // Initialiser les règles pour le user-agent par défaut
        $this->rules['*'] = [
            'allow' => [],
            'disallow' => [],
        ];

        if ($withDefaults) {
            $this->applyDefaults();
        }
    }

    /**
     * Applique les règles par défaut
     */
    private function applyDefaults(): void
    {
        $this->allow('/')
            ->disallow('/admin/')
            ->disallow('/api/')
            ->disallow('/_')
            ->disallow('/login')
            ->disallow('/logout')
            ->disallow('/register')
            ->disallow('/forgot-password')
            ->disallow('/reset-password')
            ->disallow('/verify-email');
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * DÉFINIR LE USER-AGENT
     * ═══════════════════════════════════════════════════════════════════
     * 
     * Permet de définir des règles spécifiques pour certains bots.
     * 
     * @param string $userAgent Nom du user-agent (ex: 'Googlebot', '*')
     * @return self
     * 
     * ═══════════════════════════════════════════════════════════════════
     */
    public function userAgent(string $userAgent): self
    {
        $this->currentUserAgent = $userAgent;

        if (!isset($this->rules[$userAgent])) {
            $this->rules[$userAgent] = [
                'allow' => [],
                'disallow' => [],
            ];
        }

        return $this;
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * AUTORISER UN CHEMIN
     * ═══════════════════════════════════════════════════════════════════
     * 
     * @param string $path Chemin à autoriser (ex: '/')
     * @return self
     * 
     * ═══════════════════════════════════════════════════════════════════
     */
    public function allow(string $path): self
    {
        $this->rules[$this->currentUserAgent]['allow'][] = $path;
        return $this;
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * INTERDIRE UN CHEMIN
     * ═══════════════════════════════════════════════════════════════════
     * 
     * @param string $path Chemin à interdire (ex: '/admin/')
     * @return self
     * 
     * ═══════════════════════════════════════════════════════════════════
     */
    public function disallow(string $path): self
    {
        $this->rules[$this->currentUserAgent]['disallow'][] = $path;
        return $this;
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * AJOUTER UN SITEMAP
     * ═══════════════════════════════════════════════════════════════════
     * 
     * @param string $path Chemin vers le sitemap (ex: '/sitemap.xml')
     * @return self
     * 
     * ═══════════════════════════════════════════════════════════════════
     */
    public function sitemap(string $path): self
    {
        // Construire l'URL absolue
        $url = str_starts_with($path, 'http')
            ? $path
            : $this->baseUrl . '/' . ltrim($path, '/');

        $this->sitemaps[] = $url;
        return $this;
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * DÉFINIR LE CRAWL-DELAY
     * ═══════════════════════════════════════════════════════════════════
     * 
     * @param int $seconds Délai en secondes entre les requêtes
     * @return self
     * 
     * ═══════════════════════════════════════════════════════════════════
     */
    public function crawlDelay(int $seconds): self
    {
        $this->crawlDelay = $seconds;
        return $this;
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * RÉINITIALISER LES RÈGLES
     * ═══════════════════════════════════════════════════════════════════
     * 
     * @param bool $withDefaults Réappliquer les règles par défaut
     * @return self
     * 
     * ═══════════════════════════════════════════════════════════════════
     */
    public function reset(bool $withDefaults = false): self
    {
        $this->rules = [];
        $this->sitemaps = [];
        $this->crawlDelay = null;
        $this->currentUserAgent = '*';

        $this->rules['*'] = [
            'allow' => [],
            'disallow' => [],
        ];

        if ($withDefaults) {
            $this->applyDefaults();
        }

        return $this;
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * GÉNÉRER LE CONTENU DU ROBOTS.TXT
     * ═══════════════════════════════════════════════════════════════════
     * 
     * @return string Contenu du fichier robots.txt
     * 
     * ═══════════════════════════════════════════════════════════════════
     */
    public function generate(): string
    {
        $lines = [];

        // Ajouter les règles par user-agent
        foreach ($this->rules as $userAgent => $rules) {
            $lines[] = "User-agent: {$userAgent}";

            // Crawl-delay (seulement pour user-agent générique)
            if ($this->crawlDelay !== null && $userAgent === '*') {
                $lines[] = "Crawl-delay: {$this->crawlDelay}";
            }

            // Allow rules
            foreach ($rules['allow'] as $path) {
                $lines[] = "Allow: {$path}";
            }

            // Disallow rules
            foreach ($rules['disallow'] as $path) {
                $lines[] = "Disallow: {$path}";
            }

            $lines[] = ''; // Ligne vide entre les user-agents
        }

        // Ajouter les sitemaps à la fin
        foreach ($this->sitemaps as $sitemap) {
            $lines[] = "Sitemap: {$sitemap}";
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * SAUVEGARDER LE ROBOTS.TXT
     * ═══════════════════════════════════════════════════════════════════
     * 
     * @param string $path Chemin du fichier (défaut: public/robots.txt)
     * @return bool TRUE si sauvegardé avec succès
     * 
     * ═══════════════════════════════════════════════════════════════════
     */
    public function save(string $path = 'public/robots.txt'): bool
    {
        $content = $this->generate();

        // Créer le dossier si nécessaire
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return file_put_contents($path, $content) !== false;
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * OBTENIR LES RÈGLES
     * ═══════════════════════════════════════════════════════════════════
     * 
     * @return array Règles configurées
     * 
     * ═══════════════════════════════════════════════════════════════════
     */
    public function getRules(): array
    {
        return $this->rules;
    }
}
