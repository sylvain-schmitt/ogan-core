<?php

/**
 * ═══════════════════════════════════════════════════════════════════════
 * ⚙️ CONFIG - Gestionnaire de Configuration
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * RÔLE :
 * ------
 * Centralise la gestion de la configuration de l'application.
 * Supporte plusieurs sources :
 * - Fichiers PHP (parameters.php)
 * - Variables d'environnement (.env)
 * - Valeurs par défaut
 * 
 * POURQUOI UN GESTIONNAIRE DE CONFIG ?
 * -------------------------------------
 * 
 * 1. SÉPARATION DES CONFIGURATIONS :
 *    - Développement : config/dev.php
 *    - Production : config/prod.php
 *    - Test : config/test.php
 * 
 * 2. SÉCURITÉ :
 *    - Les secrets (DB password, API keys) dans .env (non versionné)
 *    - Les configs publiques dans parameters.php (versionné)
 * 
 * 3. FLEXIBILITÉ :
 *    - Changer de config sans modifier le code
 *    - Support de différents environnements
 * 
 * EXEMPLES D'UTILISATION :
 * ------------------------
 * 
 * // Récupérer une valeur
 * $dbHost = Config::get('database.host', 'localhost');
 * 
 * // Récupérer toute une section
 * $dbConfig = Config::get('database');
 * 
 * // Vérifier si une clé existe
 * if (Config::has('app.debug')) {
 *     // Mode debug activé
 * }
 * 
 * HIÉRARCHIE DES CONFIGURATIONS :
 * --------------------------------
 * 1. Variables d'environnement (.env) → PRIORITÉ MAXIMALE
 * 2. Fichier de config PHP (parameters.php)
 * 3. Valeurs par défaut
 * 
 * ═══════════════════════════════════════════════════════════════════════
 */

namespace Ogan\Config;

class Config
{
    /**
     * @var array Configuration chargée
     */
    private static array $config = [];

    /**
     * @var bool Indique si la config a été initialisée
     */
    private static bool $initialized = false;

    /**
     * @var string Chemin racine du projet
     */
    private static string $projectRoot = '';

    /**
     * ═══════════════════════════════════════════════════════════════════
     * INITIALISER LA CONFIGURATION
     * ═══════════════════════════════════════════════════════════════════
     * 
     * Charge la configuration depuis :
     * 1. Le fichier .env (si présent)
     * 2. Le fichier parameters.yaml ou parameters.php
     * 
     * @param string $configPath Chemin vers le fichier parameters.yaml ou parameters.php
     * @param string|null $envPath Chemin vers le fichier .env (optionnel)
     * 
     * ═══════════════════════════════════════════════════════════════════
     */
    public static function init(string $configPath, ?string $envPath = null): void
    {
        if (self::$initialized) {
            return; // Déjà initialisé
        }

        // ─────────────────────────────────────────────────────────────
        // ÉTAPE 1 : Charger les fichiers .env (priorité maximale)
        // ─────────────────────────────────────────────────────────────
        // Hiérarchie : .env.local > .env
        if ($envPath === null) {
            // Chercher .env à la racine du projet
            $envPath = dirname($configPath, 2) . '/.env';
        }

        $projectRoot = dirname($envPath);
        self::$projectRoot = $projectRoot;

        // Charger .env d'abord (valeurs de base)
        if (file_exists($envPath)) {
            self::loadEnv($envPath);
        }

        // Charger .env.local ensuite (surcharge .env)
        $envLocalPath = $projectRoot . '/.env.local';
        if (file_exists($envLocalPath)) {
            self::loadEnv($envLocalPath);
        }

        // ─────────────────────────────────────────────────────────────
        // ÉTAPE 2 : Charger le fichier de configuration (YAML ou PHP)
        // ─────────────────────────────────────────────────────────────
        $configLoaded = false;

        // Essayer YAML en priorité (.yaml ou .yml)
        $yamlPath = preg_replace('/\.php$/', '.yaml', $configPath);
        if (file_exists($yamlPath)) {
            $yamlConfig = YamlParser::parseFile($yamlPath);
            if (is_array($yamlConfig)) {
                self::$config = array_merge(self::$config, $yamlConfig);
                $configLoaded = true;
            }
        } else {
            $ymlPath = preg_replace('/\.php$/', '.yml', $configPath);
            if (file_exists($ymlPath)) {
                $yamlConfig = YamlParser::parseFile($ymlPath);
                if (is_array($yamlConfig)) {
                    self::$config = array_merge(self::$config, $yamlConfig);
                    $configLoaded = true;
                }
            }
        }

        // Fallback sur PHP si YAML non trouvé
        if (!$configLoaded && file_exists($configPath)) {
            $phpConfig = require $configPath;
            if (is_array($phpConfig)) {
                self::$config = array_merge(self::$config, $phpConfig);
            }
        }

        // ─────────────────────────────────────────────────────────────
        // ÉTAPE 3 : Remplacer les valeurs par les variables d'env
        // ─────────────────────────────────────────────────────────────
        self::mergeEnvIntoConfig();

        // ─────────────────────────────────────────────────────────────
        // ÉTAPE 4 : Appliquer les défauts selon l'environnement
        // ─────────────────────────────────────────────────────────────
        self::applyEnvironmentDefaults();

        self::$initialized = true;
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * CHARGER LE FICHIER .ENV
     * ═══════════════════════════════════════════════════════════════════
     * 
     * Parse un fichier .env et charge les variables dans $_ENV.
     * 
     * FORMAT DU FICHIER .ENV :
     * ------------------------
     * APP_ENV=prod
     * APP_DEBUG=false
     * DB_HOST=localhost
     * DB_NAME=myapp
     * DB_USER=root
     * DB_PASS=secret
     * 
     * NOTES :
     * - Les lignes vides sont ignorées
     * - Les lignes commençant par # sont des commentaires
     * - Les valeurs peuvent être entre guillemets
     * 
     * ═══════════════════════════════════════════════════════════════════
     */
    private static function loadEnv(string $envPath): void
    {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Ignorer les commentaires
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            // Parser KEY=VALUE
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Enlever les guillemets
                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))
                ) {
                    $value = substr($value, 1, -1);
                }

                // Charger dans $_ENV et putenv()
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * FUSIONNER LES VARIABLES D'ENVIRONNEMENT DANS LA CONFIG
     * ═══════════════════════════════════════════════════════════════════
     * 
     * Les variables d'environnement ont la priorité sur le fichier PHP.
     * 
     * CONVENTION DE NOMMAGE :
     * -----------------------
     * Les variables d'env utilisent des underscores :
     * - APP_ENV → app.env
     * - DATABASE_URL → Parsed into database.* (Symfony-style)
     * 
     * ═══════════════════════════════════════════════════════════════════
     */
    private static function mergeEnvIntoConfig(): void
    {
        // ─────────────────────────────────────────────────────────────
        // DATABASE_URL PARSING (Symfony-style)
        // ─────────────────────────────────────────────────────────────
        // Format: mysql://user:password@host:port/database?charset=utf8mb4
        //         postgresql://user:password@host:port/database
        //         sqlite:///path/to/database.db
        if (isset($_ENV['DATABASE_URL'])) {
            $dbConfig = self::parseDatabaseUrl($_ENV['DATABASE_URL']);
            foreach ($dbConfig as $key => $value) {
                self::setNested('database.' . $key, $value);
            }
        }

        // ─────────────────────────────────────────────────────────────
        // Autres variables d'environnement
        // ─────────────────────────────────────────────────────────────
        foreach ($_ENV as $key => $value) {
            // Skip DATABASE_URL (already processed above)
            if ($key === 'DATABASE_URL') {
                continue;
            }

            // Convertir APP_ENV → app.env
            $configKey = strtolower(str_replace('_', '.', $key));

            // Convertir en structure imbriquée
            // DB_HOST → database.host (legacy support - si DATABASE_URL non défini)
            if (str_starts_with($configKey, 'db.') && !isset($_ENV['DATABASE_URL'])) {
                $configKey = 'database.' . substr($configKey, 3);
            }

            // SESSION_NAME → session.name, SESSION_LIFETIME → session.lifetime, etc.
            if (str_starts_with($configKey, 'session.')) {
                // Déjà au bon format
            } elseif (str_starts_with($configKey, 'session_')) {
                $sessionKey = strtolower(substr($configKey, 8));
                // Convertir SESSION_NAME → session.name
                // Convertir SESSION_LIFETIME → session.lifetime
                $configKey = 'session.' . $sessionKey;
            }

            // Convertir les valeurs en types appropriés
            $value = self::convertEnvValue($value);

            // Définir la valeur (les variables d'env ont la priorité)
            self::setNested($configKey, $value);
        }
    }

    /**
     * Convertit une valeur d'environnement en type PHP approprié
     * 
     * - "true", "false" → bool
     * - "null" → null
     * - nombres → int/float
     */
    private static function convertEnvValue(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $lower = strtolower(trim($value));

        // Booléens
        if ($lower === 'true' || $lower === '1' || $lower === 'on' || $lower === 'yes') {
            return true;
        }
        if ($lower === 'false' || $lower === '0' || $lower === 'off' || $lower === 'no') {
            return false;
        }

        // Null
        if ($lower === 'null' || $lower === '') {
            return null;
        }

        // Entiers
        if (ctype_digit($value) || (str_starts_with($value, '-') && ctype_digit(substr($value, 1)))) {
            return (int) $value;
        }

        // Flottants
        if (is_numeric($value)) {
            return (float) $value;
        }

        return $value;
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * APPLIQUER LES DÉFAUTS SELON L'ENVIRONNEMENT
     * ═══════════════════════════════════════════════════════════════════
     * 
     * Configure automatiquement les paramètres selon APP_ENV :
     * - dev  : debug activé, logs détaillés, session non-sécurisée
     * - prod : debug désactivé, logs minimaux, session sécurisée
     * - test : debug activé, logs warning
     * 
     * Ces valeurs sont appliquées SEULEMENT si non définies manuellement.
     * L'utilisateur peut toujours surcharger dans .env.
     * 
     * ═══════════════════════════════════════════════════════════════════
     */
    private static function applyEnvironmentDefaults(): void
    {
        // Accès direct pour éviter l'erreur "non initialisé"
        $env = self::$config['app']['env'] ?? 'dev';

        // Valider APP_SECRET en production
        if ($env === 'prod') {
            $secret = self::$config['app']['secret'] ?? null;
            if (empty($secret) || $secret === 'changeme-in-production') {
                throw new \RuntimeException(
                    'APP_SECRET doit être défini en production. ' .
                        'Générez une clé avec: php -r "echo bin2hex(random_bytes(32));"'
                );
            }
        }

        // Défauts selon l'environnement
        $defaults = match ($env) {
            'prod' => [
                'app.debug' => false,
                'session.secure' => true,
                'session.httponly' => true,
                'session.samesite' => 'Strict',
                'session.lifetime' => 3600,
                'session.name' => 'OGAN_SESS',
                'log.level' => 'error',
                'cache.enabled' => true,
            ],
            'test' => [
                'app.debug' => true,
                'session.secure' => false,
                'session.httponly' => true,
                'session.samesite' => 'Lax',
                'session.lifetime' => 7200,
                'session.name' => 'OGAN_TEST',
                'log.level' => 'warning',
                'cache.enabled' => false,
            ],
            default => [ // dev
                'app.debug' => true,
                'session.secure' => false,
                'session.httponly' => true,
                'session.samesite' => 'Lax',
                'session.lifetime' => 7200,
                'session.name' => 'OGAN_DEV',
                'log.level' => 'debug',
                'cache.enabled' => false,
                'mailer.dsn' => 'smtp://127.0.0.1:1025', // MailHog par défaut
            ],
        };

        // Défauts communs à tous les environnements (chemins absolus)
        $commonDefaults = [
            'session.path' => '/',
            'session.domain' => '',
            'log.path' => self::$projectRoot . '/var/log',
            'cache.path' => self::$projectRoot . '/var/cache',
            'router.base.path' => '',
            'view.templates_path' => self::$projectRoot . '/templates',
        ];

        // Fusionner défauts communs
        $defaults = array_merge($commonDefaults, $defaults);

        // Appliquer les défauts SEULEMENT si non définis
        foreach ($defaults as $key => $value) {
            if (!self::has($key)) {
                self::setNested($key, $value);
            }
        }
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * PARSER UNE DATABASE_URL (FORMAT SYMFONY)
     * ═══════════════════════════════════════════════════════════════════
     * 
     * Supporte les formats :
     * - mysql://user:password@host:port/database?charset=utf8mb4
     * - postgresql://user:password@host:port/database  
     * - pgsql://user:password@host:port/database
     * - sqlite:///path/to/database.db
     * - sqlite:///%kernel.project_dir%/var/app.db
     * 
     * @param string $url DATABASE_URL
     * @return array Configuration parsée
     * 
     * ═══════════════════════════════════════════════════════════════════
     */
    private static function parseDatabaseUrl(string $url): array
    {
        // SQLite special handling
        if (str_starts_with($url, 'sqlite:')) {
            // Format: sqlite:///path/to/db.sqlite ou sqlite:///var/app.db
            $path = preg_replace('#^sqlite:///+#', '', $url);

            // Remplacer %kernel.project_dir% par PROJECT_ROOT
            if (defined('PROJECT_ROOT')) {
                $path = str_replace('%kernel.project_dir%', PROJECT_ROOT, $path);
            }

            return [
                'driver' => 'sqlite',
                'name' => $path,
            ];
        }

        // Parse URL standard
        $parsed = parse_url($url);

        if ($parsed === false) {
            throw new \InvalidArgumentException("DATABASE_URL invalide: {$url}");
        }

        // Map scheme to driver
        $driverMap = [
            'mysql' => 'mysql',
            'mariadb' => 'mysql',
            'postgresql' => 'pgsql',
            'pgsql' => 'pgsql',
            'postgres' => 'pgsql',
            'sqlsrv' => 'sqlsrv',
            'mssql' => 'sqlsrv',
        ];

        $scheme = $parsed['scheme'] ?? '';
        $driver = $driverMap[$scheme] ?? $scheme;

        // Extract database name from path
        $dbname = ltrim($parsed['path'] ?? '', '/');

        // Parse query string for options like charset
        $options = [];
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $options);
        }

        $config = [
            'driver' => $driver,
            'host' => $parsed['host'] ?? 'localhost',
            'port' => $parsed['port'] ?? null,
            'name' => $dbname,
            'user' => isset($parsed['user']) ? urldecode($parsed['user']) : 'root',
            'password' => isset($parsed['pass']) ? urldecode($parsed['pass']) : '',
        ];

        // Add charset if specified
        if (isset($options['charset'])) {
            $config['charset'] = $options['charset'];
        } elseif ($driver === 'mysql') {
            $config['charset'] = 'utf8mb4'; // Default for MySQL
        }

        // Add serverVersion if specified (useful for Doctrine compatibility)
        if (isset($options['serverVersion'])) {
            $config['serverVersion'] = $options['serverVersion'];
        }

        return $config;
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * DÉFINIR UNE VALEUR IMBRIQUÉE
     * ═══════════════════════════════════════════════════════════════════
     * 
     * Permet de définir database.host au lieu de ['database']['host'].
     * 
     * EXEMPLE :
     * ---------
     * setNested('database.host', 'localhost')
     * → $config['database']['host'] = 'localhost'
     * 
     * ═══════════════════════════════════════════════════════════════════
     */
    private static function setNested(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $config = &self::$config;

        foreach ($keys as $k) {
            if (!isset($config[$k]) || !is_array($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * RÉCUPÉRER UNE VALEUR DE CONFIGURATION
     * ═══════════════════════════════════════════════════════════════════
     * 
     * Récupère une valeur de configuration avec support de clés imbriquées.
     * 
     * EXEMPLES :
     * ----------
     * Config::get('app.env')           → 'prod'
     * Config::get('database.host')     → 'localhost'
     * Config::get('database')           → ['host' => 'localhost', ...]
     * Config::get('missing', 'default') → 'default'
     * 
     * @param string $key Clé de configuration (supporte la notation point)
     * @param mixed $default Valeur par défaut si la clé n'existe pas
     * @return mixed La valeur de configuration ou la valeur par défaut
     * 
     * ═══════════════════════════════════════════════════════════════════
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (!self::$initialized) {
            throw new \RuntimeException('Config n\'a pas été initialisée. Appelez Config::init() d\'abord.');
        }

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * VÉRIFIER SI UNE CLÉ EXISTE
     * ═══════════════════════════════════════════════════════════════════
     * 
     * @param string $key Clé de configuration
     * @return bool TRUE si la clé existe, FALSE sinon
     * 
     * ═══════════════════════════════════════════════════════════════════
     */
    public static function has(string $key): bool
    {
        if (!self::$initialized) {
            return false;
        }

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return false;
            }
            $value = $value[$k];
        }

        return true;
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * DÉFINIR UNE VALEUR DE CONFIGURATION
     * ═══════════════════════════════════════════════════════════════════
     * 
     * Utile pour les tests ou pour modifier la config à la volée.
     * 
     * @param string $key Clé de configuration
     * @param mixed $value Valeur à définir
     * 
     * ═══════════════════════════════════════════════════════════════════
     */
    public static function set(string $key, mixed $value): void
    {
        if (!self::$initialized) {
            self::$config = [];
            self::$initialized = true;
        }

        self::setNested($key, $value);
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * RÉCUPÉRER TOUTE LA CONFIGURATION
     * ═══════════════════════════════════════════════════════════════════
     * 
     * @return array Toute la configuration
     * 
     * ═══════════════════════════════════════════════════════════════════
     */
    public static function all(): array
    {
        if (!self::$initialized) {
            return [];
        }

        return self::$config;
    }
}

/**
 * ═══════════════════════════════════════════════════════════════════════
 * 📚 NOTES PÉDAGOGIQUES
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * POURQUOI UNE CLASSE STATIQUE ?
 * --------------------------------
 * 
 * Config est une classe statique car :
 * 1. Il n'y a qu'UNE SEULE configuration pour toute l'application
 * 2. On veut y accéder facilement : Config::get('key')
 * 3. Pas besoin d'instancier plusieurs fois
 * 
 * ALTERNATIVE : Singleton Pattern
 * --------------------------------
 * 
 * On pourrait aussi utiliser un singleton :
 * 
 * $config = Config::getInstance();
 * $config->get('key');
 * 
 * Mais la classe statique est plus simple pour ce cas d'usage.
 * 
 * SÉCURITÉ DES VARIABLES D'ENVIRONNEMENT
 * ---------------------------------------
 * 
 * ⚠️ IMPORTANT : Ne JAMAIS commiter le fichier .env dans Git !
 * 
 * Le fichier .env contient des secrets :
 * - Mots de passe de base de données
 * - Clés API
 * - Tokens d'authentification
 * 
 * Ajouter .env dans .gitignore :
 * 
 * # .gitignore
 * .env
 * .env.local
 * 
 * HIÉRARCHIE DES CONFIGURATIONS
 * ------------------------------
 * 
 * 1. Variables d'environnement (.env) → PRIORITÉ MAXIMALE
 *    Utile pour : secrets, configs spécifiques à l'environnement
 * 
 * 2. Fichier PHP (parameters.php) → PRIORITÉ MOYENNE
 *    Utile pour : configs par défaut, structure de l'app
 * 
 * 3. Valeurs par défaut dans le code → PRIORITÉ MINIMALE
 *    Utile pour : fallback, valeurs sûres
 * 
 * EXEMPLE D'UTILISATION DANS LE KERNEL
 * -------------------------------------
 * 
 * // Dans Kernel.php
 * Config::init(__DIR__ . '/../config/parameters.php');
 * 
 * $debug = Config::get('app.debug', false);
 * $dbHost = Config::get('database.host', 'localhost');
 * 
 * ═══════════════════════════════════════════════════════════════════════
 */
