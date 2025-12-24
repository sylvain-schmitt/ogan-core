<?php

/**
 * ═══════════════════════════════════════════════════════════════════════
 * 🗺️ SITEMAP GENERATOR - Génération de sitemap.xml pour SEO
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * RÔLE :
 * ------
 * Génère un fichier sitemap.xml conforme au protocole Sitemaps.
 * Compatible avec Google Search Console, Bing Webmaster Tools, etc.
 * 
 * EXEMPLES D'UTILISATION :
 * ------------------------
 * 
 * // Création manuelle
 * $sitemap = new SitemapGenerator('https://example.com');
 * $sitemap->addUrl('/')
 *         ->addUrl('/about', priority: 0.8)
 *         ->addUrl('/contact', changefreq: 'monthly')
 *         ->save();
 * 
 * // Génération automatique depuis les routes
 * $sitemap = new SitemapGenerator('https://example.com');
 * $sitemap->addRoutesFromRouter($router)
 *         ->save();
 * 
 * ═══════════════════════════════════════════════════════════════════════
 */

namespace Ogan\Seo;

use Ogan\Router\Router;

class SitemapGenerator
{
    /**
     * @var string Base URL du site
     */
    private string $baseUrl;

    /**
     * @var array Liste des URLs à inclure
     */
    private array $urls = [];

    /**
     * @var array Routes à exclure du sitemap automatique
     */
    private array $excludePatterns = [
        '/admin*',
        '/api*',
        '/_*',
        '/login',
        '/logout',
        '/register',
        '/forgot-password',
        '/reset-password*',
        '/verify-email*',
    ];

    /**
     * ═══════════════════════════════════════════════════════════════════
     * CONSTRUCTEUR
     * ═══════════════════════════════════════════════════════════════════
     * 
     * @param string $baseUrl URL de base du site (ex: https://example.com)
     * 
     * ═══════════════════════════════════════════════════════════════════
     */
    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * AJOUTER UNE URL MANUELLEMENT
     * ═══════════════════════════════════════════════════════════════════
     * 
     * @param string $path Chemin de l'URL (ex: /about)
     * @param string|null $lastmod Date de dernière modification (ISO 8601)
     * @param string $changefreq Fréquence de changement (always, hourly, daily, weekly, monthly, yearly, never)
     * @param float $priority Priorité (0.0 à 1.0)
     * @return self
     * 
     * ═══════════════════════════════════════════════════════════════════
     */
    public function addUrl(
        string $path,
        ?string $lastmod = null,
        string $changefreq = 'weekly',
        float $priority = 0.5
    ): self {
        $this->urls[] = [
            'loc' => $this->baseUrl . '/' . ltrim($path, '/'),
            'lastmod' => $lastmod ?? date('Y-m-d'),
            'changefreq' => $changefreq,
            'priority' => $priority,
        ];

        return $this;
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * AJOUTER UNE ROUTE PAR NOM
     * ═══════════════════════════════════════════════════════════════════
     * 
     * @param Router $router Instance du router
     * @param string $routeName Nom de la route
     * @param array $params Paramètres de la route
     * @param string|null $lastmod Date de dernière modification
     * @param string $changefreq Fréquence de changement
     * @param float $priority Priorité
     * @return self
     * 
     * ═══════════════════════════════════════════════════════════════════
     */
    public function addRoute(
        Router $router,
        string $routeName,
        array $params = [],
        ?string $lastmod = null,
        string $changefreq = 'weekly',
        float $priority = 0.5
    ): self {
        try {
            $path = $router->generateUrl($routeName, $params);
            $this->addUrl($path, $lastmod, $changefreq, $priority);
        } catch (\Exception $e) {
            // Route non trouvée, ignorer silencieusement
        }

        return $this;
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * AJOUTER AUTOMATIQUEMENT LES ROUTES DU ROUTER
     * ═══════════════════════════════════════════════════════════════════
     * 
     * Ajoute toutes les routes GET qui ne correspondent pas aux patterns exclus.
     * 
     * @param Router $router Instance du router
     * @param float $defaultPriority Priorité par défaut
     * @return self
     * 
     * ═══════════════════════════════════════════════════════════════════
     */
    public function addRoutesFromRouter(Router $router, float $defaultPriority = 0.5): self
    {
        $routes = $router->getRoutes();

        foreach ($routes as $route) {
            // Seulement les routes GET
            $methods = $route['methods'] ?? ['GET'];
            if (!in_array('GET', $methods)) {
                continue;
            }

            $path = $route['path'] ?? '';
            $name = $route['name'] ?? '';

            // Ignorer les routes avec paramètres dynamiques (ex: /user/{id})
            if (preg_match('/\{[^}]+\}/', $path)) {
                continue;
            }

            // Vérifier les patterns d'exclusion
            if ($this->isExcluded($path)) {
                continue;
            }

            // Déterminer la priorité
            $priority = $defaultPriority;
            if ($path === '/' || $name === 'index' || $name === 'home') {
                $priority = 1.0;
            }

            $this->addUrl($path, null, 'weekly', $priority);
        }

        return $this;
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * DÉFINIR LES PATTERNS D'EXCLUSION
     * ═══════════════════════════════════════════════════════════════════
     * 
     * @param array $patterns Patterns glob à exclure (ex: '/admin*')
     * @return self
     * 
     * ═══════════════════════════════════════════════════════════════════
     */
    public function setExcludePatterns(array $patterns): self
    {
        $this->excludePatterns = $patterns;
        return $this;
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * AJOUTER UN PATTERN D'EXCLUSION
     * ═══════════════════════════════════════════════════════════════════
     * 
     * @param string $pattern Pattern glob à exclure
     * @return self
     * 
     * ═══════════════════════════════════════════════════════════════════
     */
    public function addExcludePattern(string $pattern): self
    {
        $this->excludePatterns[] = $pattern;
        return $this;
    }

    /**
     * Vérifie si un path correspond aux patterns d'exclusion
     */
    private function isExcluded(string $path): bool
    {
        foreach ($this->excludePatterns as $pattern) {
            if (fnmatch($pattern, $path)) {
                return true;
            }
        }
        return false;
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * GÉNÉRER LE XML DU SITEMAP
     * ═══════════════════════════════════════════════════════════════════
     * 
     * @return string Contenu XML du sitemap
     * 
     * ═══════════════════════════════════════════════════════════════════
     */
    public function generate(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

        foreach ($this->urls as $url) {
            $xml .= '  <url>' . PHP_EOL;
            $xml .= '    <loc>' . htmlspecialchars($url['loc']) . '</loc>' . PHP_EOL;
            $xml .= '    <lastmod>' . $url['lastmod'] . '</lastmod>' . PHP_EOL;
            $xml .= '    <changefreq>' . $url['changefreq'] . '</changefreq>' . PHP_EOL;
            $xml .= '    <priority>' . number_format($url['priority'], 1) . '</priority>' . PHP_EOL;
            $xml .= '  </url>' . PHP_EOL;
        }

        $xml .= '</urlset>' . PHP_EOL;

        return $xml;
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * SAUVEGARDER LE SITEMAP
     * ═══════════════════════════════════════════════════════════════════
     * 
     * @param string $path Chemin du fichier (défaut: public/sitemap.xml)
     * @return bool TRUE si sauvegardé avec succès
     * 
     * ═══════════════════════════════════════════════════════════════════
     */
    public function save(string $path = 'public/sitemap.xml'): bool
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
     * OBTENIR LES URLS AJOUTÉES
     * ═══════════════════════════════════════════════════════════════════
     * 
     * @return array Liste des URLs
     * 
     * ═══════════════════════════════════════════════════════════════════
     */
    public function getUrls(): array
    {
        return $this->urls;
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * VIDER LES URLS
     * ═══════════════════════════════════════════════════════════════════
     * 
     * @return self
     * 
     * ═══════════════════════════════════════════════════════════════════
     */
    public function clear(): self
    {
        $this->urls = [];
        return $this;
    }
}
