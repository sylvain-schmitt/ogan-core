<?php

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * 🚨 ERRORHANDLER - Gestionnaire Global d'Erreurs
 * ═══════════════════════════════════════════════════════════════════════════
 * 
 * RÔLE
 * ----
 * Capture TOUTES les erreurs et exceptions de l'application et les affiche
 * de manière propre et utile.
 * 
 * TYPES D'ERREURS GÉRÉES
 * -----------------------
 * 1. Exceptions non catchées (throw new Exception())
 * 2. Erreurs fatales PHP (parse error, call to undefined function...)
 * 3. Warnings et notices PHP (si configuré)
 * 
 * MODES D'AFFICHAGE
 * -----------------
 * - **DEV** : Affichage complet (stack trace, fichier, ligne, code source, variables...)
 * - **PROD** : Page d'erreur générique sans détails techniques (sécurité)
 * 
 * ═══════════════════════════════════════════════════════════════════════════
 */

namespace Ogan\Error;

use Throwable;
use Ogan\Exception\RouteNotFoundException;
use Ogan\DependencyInjection\Container;

class ErrorHandler
{
    private bool $debug;

    /**
     * Container statique pour permettre l'accès aux services depuis les templates d'erreur
     * Injecté par le Kernel après boot() pour que les templates puissent utiliser extend(), route(), etc.
     */
    private static ?Container $container = null;

    /**
     * @param bool $debug Mode debug (true = dev, false = prod)
     */
    public function __construct(bool $debug = false)
    {
        $this->debug = $debug;
    }

    /**
     * Injecte le Container pour permettre le rendu complet des templates d'erreur
     * Appelé par le Kernel après boot()
     */
    public static function setContainer(Container $container): void
    {
        self::$container = $container;
    }

    /**
     * Retourne le Container si disponible
     */
    public static function getContainer(): ?Container
    {
        return self::$container;
    }

    /**
     * Vérifie si la requête actuelle est une requête HTMX
     */
    private function isHtmxRequest(): bool
    {
        return isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true';
    }

    /**
     * Enregistre le handler comme gestionnaire global
     */
    public function register(): void
    {
        // Capture les exceptions non catchées
        set_exception_handler([$this, 'handleException']);

        // Convertit les erreurs PHP (warnings, notices...) en exceptions
        set_error_handler([$this, 'handleError']);

        // Capture les erreurs fatales (parse error, etc.)
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Gère les exceptions non catchées
     */
    public function handleException(Throwable $exception): void
    {
        // ═══════════════════════════════════════════════════════════════
        // LOGGING AUTOMATIQUE - Comme Symfony/Monolog
        // ═══════════════════════════════════════════════════════════════
        if (function_exists('log_exception')) {
            log_exception($exception);
        }

        // Détermine le code HTTP selon le type d'exception
        $statusCode = $this->getStatusCode($exception);
        http_response_code($statusCode);

        // ═══════════════════════════════════════════════════════════════
        // HTMX SUPPORT
        // ═══════════════════════════════════════════════════════════════
        // En mode DEBUG : afficher la page d'erreur complète (utile pour déboguer)
        // En mode PROD : afficher un toast discret
        if ($this->isHtmxRequest()) {
            if ($this->debug) {
                // Mode debug : renvoyer la page complète avec script pour remplacer le document
                $this->renderHtmxDebugPage($exception);
            } else {
                // Mode production : renvoyer un toast discret
                $this->renderHtmxError($exception, $statusCode);
            }
            exit(1);
        }

        if ($this->debug) {
            $this->renderDebugPage($exception);
        } else {
            $this->renderProductionPage($exception, $statusCode);
        }

        exit(1);
    }

    /**
     * Convertit les erreurs PHP en exceptions
     */
    public function handleError(int $severity, string $message, string $file = '', int $line = 0): bool
    {
        // Ne pas convertir les erreurs supprimées avec @
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    /**
     * Gère les erreurs fatales
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();

        // Vérifier si c'est une erreur fatale
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->handleException(
                new \ErrorException(
                    $error['message'],
                    0,
                    $error['type'],
                    $error['file'],
                    $error['line']
                )
            );
        }
    }

    /**
     * Détermine le code HTTP selon le type d'exception
     */
    private function getStatusCode(Throwable $exception): int
    {
        // Le code d'exception peut être une string (ex: "0" ou "HY000" pour PDO)
        // On le convertit en int de manière sécurisée
        $code = $exception->getCode();

        // Si c'est une string, essayer de la convertir
        if (is_string($code)) {
            $code = (int) $code;
        }

        // Si l'exception a un code HTTP valide (400-599)
        if (is_int($code) && $code >= 400 && $code < 600) {
            return $code;
        }

        // Selon le type d'exception
        if ($exception instanceof RouteNotFoundException) {
            return 404;
        }

        // Par défaut : 500 Internal Server Error
        return 500;
    }

    /**
     * Affiche la page d'erreur complète pour les requêtes HTMX (mode debug)
     * 
     * Génère la page debug et l'envoie avec un script JavaScript
     * qui remplace entièrement le document actuel. Cela permet de voir
     * l'erreur complète même quand HTMX intercepte la requête.
     */
    private function renderHtmxDebugPage(Throwable $exception): void
    {
        // Nettoyer les buffers de sortie
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Générer le contenu de la page debug
        $debugPageHtml = $this->generateDebugPageHtml($exception);

        // Échapper le HTML pour JavaScript
        $escapedHtml = json_encode($debugPageHtml);

        // Envoyer un script qui remplace tout le document
        echo <<<HTML
<script>
(function() {
    var errorHtml = {$escapedHtml};
    document.open();
    document.write(errorHtml);
    document.close();
})();
</script>
HTML;
    }

    /**
     * Génère le HTML de la page debug (sans l'afficher)
     */
    private function generateDebugPageHtml(Throwable $exception): string
    {
        $class = get_class($exception);
        $message = htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
        $file = $exception->getFile();
        $line = $exception->getLine();
        $shortClass = substr(strrchr($class, '\\'), 1) ?: $class;
        $codeExcerpt = $this->getCodeExcerpt($file, $line);
        $enhancedTrace = $this->getEnhancedStackTrace($exception);
        $contextVars = $this->getContextVariables();
        $fileHtml = htmlspecialchars($file, ENT_QUOTES, 'UTF-8');
        $classHtml = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erreur - {$shortClass}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'JetBrains Mono', 'Fira Code', Monaco, monospace; 
            background: linear-gradient(135deg, #1e1e2e 0%, #2d2d3f 100%);
            color: #cdd6f4;
            min-height: 100vh;
            line-height: 1.5;
        }
        .container { 
            width: 95%; 
            max-width: 1600px; 
            margin: 0 auto; 
            padding: 20px; 
            box-sizing: border-box;
        }
        .header {
            background: linear-gradient(135deg, #f38ba8 0%, #fab387 100%);
            color: #1e1e2e;
            padding: 24px 32px;
            border-radius: 12px 12px 0 0;
            margin-bottom: 0;
        }
        .header h1 { font-size: 24px; font-weight: 700; margin-bottom: 8px; }
        .header .exception-class { 
            background: rgba(0,0,0,0.2); 
            padding: 4px 12px; 
            border-radius: 6px; 
            font-size: 14px;
        }
        .message-box {
            background: #313244;
            border-left: 4px solid #f9e2af;
            padding: 20px 24px;
            font-size: 16px;
            word-break: break-word;
        }
        .content { background: #1e1e2e; padding: 24px; border-radius: 0 0 12px 12px; }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .info-item { background: #313244; padding: 12px 16px; border-radius: 8px; }
        .info-label { color: #a6adc8; font-size: 11px; text-transform: uppercase; margin-bottom: 4px; }
        .info-value { color: #89b4fa; font-size: 13px; word-break: break-all; }
        .section { margin-bottom: 24px; }
        .section-title { 
            color: #89b4fa; 
            font-size: 14px; 
            font-weight: 600; 
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .code-excerpt { 
            background: #11111b; 
            border-radius: 8px; 
            overflow: hidden;
            font-size: 12px;
        }
        .code-line { 
            display: flex; 
            padding: 2px 0;
            border-left: 3px solid transparent;
        }
        .code-line.error-line { 
            background: rgba(243, 139, 168, 0.15); 
            border-left-color: #f38ba8;
        }
        .line-marker { width: 24px; text-align: center; color: #f38ba8; }
        .line-num { 
            width: 48px; 
            text-align: right; 
            padding-right: 16px; 
            color: #6c7086;
            user-select: none;
        }
        .line-code { flex: 1; white-space: pre; overflow-x: auto; }
        .trace-frame { 
            background: #313244; 
            margin-bottom: 4px; 
            border-radius: 6px; 
            overflow: hidden;
        }
        .trace-header { 
            display: flex; 
            gap: 12px; 
            padding: 10px 14px; 
            cursor: pointer;
            transition: background 0.2s;
        }
        .trace-header:hover { background: #45475a; }
        .trace-num { color: #f38ba8; font-weight: bold; min-width: 30px; }
        .trace-call { color: #a6e3a1; flex: 1; }
        .trace-location { color: #89b4fa; font-size: 12px; }
        .trace-code { padding: 0 14px 14px; }
        .trace-code .code-excerpt { font-size: 11px; }
        .hidden { display: none; }
        .context-section { margin-bottom: 8px; }
        .context-section summary {
            background: #313244;
            padding: 10px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
        }
        .context-section summary:hover { background: #45475a; }
        .context-section[open] summary { border-radius: 6px 6px 0 0; }
        .var-table { 
            width: 100%; 
            background: #11111b; 
            border-radius: 0 0 6px 6px;
            font-size: 12px;
        }
        .var-table td { padding: 8px 12px; border-bottom: 1px solid #313244; }
        .var-key { color: #89dceb; width: 200px; }
        .var-value { color: #f9e2af; word-break: break-all; }
        .var-value pre { margin: 0; font-size: 11px; max-height: 150px; overflow: auto; }
        .bool { color: #fab387; }
        .null { color: #6c7086; font-style: italic; }
        .empty { color: #6c7086; font-style: italic; display: block; padding: 12px; }
        .footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 0;
            color: #6c7086;
            font-size: 12px;
        }
        .copy-btn {
            background: #45475a;
            color: #cdd6f4;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-family: inherit;
            font-size: 12px;
        }
        .copy-btn:hover { background: #585b70; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚨 Une erreur s'est produite</h1>
            <span class="exception-class">{$shortClass}</span>
        </div>
        
        <div class="message-box">{$message}</div>
        
        <div class="content">
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Fichier</div>
                    <div class="info-value">{$fileHtml}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Ligne</div>
                    <div class="info-value">{$line}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Classe</div>
                    <div class="info-value">{$classHtml}</div>
                </div>
            </div>
            
            <div class="section">
                <div class="section-title">📄 Code Source</div>
                {$codeExcerpt}
            </div>
            
            <div class="section">
                <div class="section-title">📚 Stack Trace (cliquez pour voir le code)</div>
                {$enhancedTrace}
            </div>
            
            <div class="section">
                <div class="section-title">🔍 Variables de Contexte</div>
                {$contextVars}
            </div>
        </div>
        
        <div class="footer">
            <span>Framework Ogan 🐕 | Mode DEBUG</span>
            <button class="copy-btn" onclick="copyError()">📋 Copier l'erreur</button>
        </div>
    </div>
    
    <script>
    function copyError() {
        var exClass = document.querySelector('.exception-class').textContent;
        var message = document.querySelector('.message-box').textContent;
        var fileInfo = document.querySelector('.info-value').textContent;
        var text = exClass + ": " + message + "\\n" + "File: " + fileInfo;
        navigator.clipboard.writeText(text).then(function() {
            alert('Erreur copiée !');
        });
    }
    </script>
</body>
</html>
HTML;
    }

    /**
     * Affiche une erreur adaptée aux requêtes HTMX (fragment HTML)
     * 
     * Renvoie un fragment HTML stylisé qui peut être injecté dans la page
     * via HX-Retarget pour afficher une notification toast.
     */
    private function renderHtmxError(Throwable $exception, int $statusCode): void
    {
        // Nettoyer les buffers de sortie
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Headers HTMX pour cibler le container de notifications
        header('HX-Retarget: #htmx-error-container');
        header('HX-Reswap: innerHTML');

        $class = get_class($exception);
        $shortClass = substr(strrchr($class, '\\'), 1) ?: $class;
        $message = htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');

        // Styles CSS inline pour éviter la dépendance à Tailwind
        $styles = [
            'toast' => 'position: fixed; top: 1rem; right: 1rem; z-index: 9999; max-width: 32rem; background-color: #7f1d1d; border: 1px solid #b91c1c; border-radius: 0.5rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); padding: 1rem; font-family: system-ui, -apple-system, sans-serif;',
            'flex' => 'display: flex; align-items: flex-start; gap: 0.75rem;',
            'icon_box' => 'flex-shrink: 0;',
            'icon' => 'width: 1.5rem; height: 1.5rem; color: #f87171;',
            'content' => 'flex: 1; min-width: 0;',
            'title' => 'font-size: 0.875rem; font-weight: 700; color: #fecaca; margin: 0;',
            'text' => 'font-size: 0.875rem; color: #fca5a5; margin: 0.25rem 0 0 0; line-height: 1.4;',
            'meta' => 'font-size: 0.75rem; color: #f87171; margin: 0.5rem 0 0 0; font-family: monospace; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;',
            'close_btn' => 'flex-shrink: 0; color: #f87171; background: none; border: none; cursor: pointer; padding: 0; outline: none; display: flex; align-items: center; justify-content: center;',
            'close_icon' => 'width: 1.25rem; height: 1.25rem;'
        ];

        if ($this->debug) {
            $file = htmlspecialchars($exception->getFile(), ENT_QUOTES, 'UTF-8');
            $line = $exception->getLine();

            echo <<<HTML
<div id="htmx-error-toast" style="{$styles['toast']}" class="animate-slide-in">
    <div style="{$styles['flex']}">
        <div style="{$styles['icon_box']}">
            <svg style="{$styles['icon']}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
        </div>
        <div style="{$styles['content']}">
            <p style="{$styles['title']}">{$shortClass} ({$statusCode})</p>
            <p style="{$styles['text']}">{$message}</p>
            <p style="{$styles['meta']}">{$file}:{$line}</p>
        </div>
        <button onclick="this.closest('#htmx-error-toast').remove()" style="{$styles['close_btn']}">
            <svg style="{$styles['close_icon']}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
</div>
<style>
@keyframes slide-in { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
.animate-slide-in { animation: slide-in 0.3s ease-out; }
</style>
HTML;
        } else {
            // Mode production : message générique
            $title = match ($statusCode) {
                403 => 'Accès refusé',
                404 => 'Non trouvé',
                default => 'Erreur',
            };

            // Ajustement style pour prod (centré verticalement)
            $styles['flex'] = 'display: flex; align-items: center; gap: 0.75rem;';
            $styles['toast'] = str_replace('max-width: 32rem', 'max-width: 28rem', $styles['toast']);

            echo <<<HTML
<div id="htmx-error-toast" style="{$styles['toast']}" class="animate-slide-in">
    <div style="{$styles['flex']}">
        <div style="{$styles['icon_box']}">
            <svg style="{$styles['icon']}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
        </div>
        <div style="{$styles['content']}">
            <p style="{$styles['title']}">{$title}</p>
            <p style="{$styles['text']}">Une erreur s'est produite. Veuillez réessayer.</p>
        </div>
        <button onclick="this.closest('#htmx-error-toast').remove()" style="{$styles['close_btn']}">
            <svg style="{$styles['close_icon']}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
</div>
<style>
@keyframes slide-in { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
.animate-slide-in { animation: slide-in 0.3s ease-out; }
</style>
HTML;
        }
    }

    /**
     * Extrait le code source autour de la ligne d'erreur
     */
    private function getCodeExcerpt(string $file, int $errorLine, int $context = 8): string
    {
        if (!file_exists($file) || !is_readable($file)) {
            return '<em>Impossible de lire le fichier</em>';
        }

        $lines = file($file);
        if ($lines === false) {
            return '<em>Impossible de lire le fichier</em>';
        }

        $start = max(0, $errorLine - $context - 1);
        $end = min(count($lines), $errorLine + $context);

        $html = '<div class="code-excerpt">';
        for ($i = $start; $i < $end; $i++) {
            $lineNum = $i + 1;
            $lineContent = htmlspecialchars($lines[$i], ENT_QUOTES, 'UTF-8');
            $lineContent = rtrim($lineContent);

            $isError = ($lineNum === $errorLine);
            $class = $isError ? 'error-line' : '';
            $marker = $isError ? '→' : '  ';

            $html .= sprintf(
                '<div class="code-line %s"><span class="line-marker">%s</span><span class="line-num">%d</span><span class="line-code">%s</span></div>',
                $class,
                $marker,
                $lineNum,
                $lineContent ?: ' '
            );
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Génère le stack trace amélioré avec code source
     */
    private function getEnhancedStackTrace(Throwable $exception): string
    {
        $trace = $exception->getTrace();
        $html = '';

        foreach ($trace as $i => $frame) {
            $file = $frame['file'] ?? 'unknown';
            $line = $frame['line'] ?? 0;
            $function = $frame['function'] ?? '';
            $class = $frame['class'] ?? '';
            $type = $frame['type'] ?? '';

            $shortFile = basename($file);
            $call = $class ? "{$class}{$type}{$function}()" : "{$function}()";

            $html .= '<div class="trace-frame">';
            $html .= '<div class="trace-header" onclick="this.nextElementSibling.classList.toggle(\'hidden\')">';
            $html .= '<span class="trace-num">#' . $i . '</span>';
            $html .= '<span class="trace-call">' . htmlspecialchars($call) . '</span>';
            $html .= '<span class="trace-location">' . htmlspecialchars($shortFile) . ':' . $line . '</span>';
            $html .= '</div>';

            if ($file !== 'unknown' && file_exists($file)) {
                $html .= '<div class="trace-code hidden">';
                $html .= $this->getCodeExcerpt($file, $line, 3);
                $html .= '</div>';
            }

            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Génère l'affichage des variables de contexte
     */
    private function getContextVariables(): string
    {
        $html = '<div class="context-tabs">';

        // GET
        $html .= '<details class="context-section">';
        $html .= '<summary>$_GET (' . count($_GET) . ')</summary>';
        $html .= $this->renderVariableTable($_GET);
        $html .= '</details>';

        // POST
        $html .= '<details class="context-section">';
        $html .= '<summary>$_POST (' . count($_POST) . ')</summary>';
        $html .= $this->renderVariableTable($_POST);
        $html .= '</details>';

        // SESSION
        if (session_status() === PHP_SESSION_ACTIVE) {
            $html .= '<details class="context-section">';
            $html .= '<summary>$_SESSION (' . count($_SESSION) . ')</summary>';
            $html .= $this->renderVariableTable($_SESSION);
            $html .= '</details>';
        }

        // COOKIES
        $html .= '<details class="context-section">';
        $html .= '<summary>$_COOKIE (' . count($_COOKIE) . ')</summary>';
        $html .= $this->renderVariableTable($_COOKIE);
        $html .= '</details>';

        // SERVER (filtered)
        $serverFiltered = array_filter($_SERVER, function ($key) {
            return in_array($key, [
                'REQUEST_METHOD',
                'REQUEST_URI',
                'HTTP_HOST',
                'HTTP_USER_AGENT',
                'REMOTE_ADDR',
                'SERVER_NAME',
                'CONTENT_TYPE',
                'HTTP_ACCEPT'
            ]);
        }, ARRAY_FILTER_USE_KEY);
        $html .= '<details class="context-section">';
        $html .= '<summary>$_SERVER (filtered)</summary>';
        $html .= $this->renderVariableTable($serverFiltered);
        $html .= '</details>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Rend un tableau de variables
     */
    private function renderVariableTable(array $vars): string
    {
        if (empty($vars)) {
            return '<em class="empty">Aucune donnée</em>';
        }

        $html = '<table class="var-table">';
        foreach ($vars as $key => $value) {
            $keyHtml = htmlspecialchars((string)$key);
            if (is_array($value) || is_object($value)) {
                $valueHtml = '<pre>' . htmlspecialchars(print_r($value, true)) . '</pre>';
            } elseif (is_bool($value)) {
                $valueHtml = $value ? '<span class="bool">true</span>' : '<span class="bool">false</span>';
            } elseif (is_null($value)) {
                $valueHtml = '<span class="null">null</span>';
            } else {
                $valueHtml = htmlspecialchars((string)$value);
            }
            $html .= "<tr><td class=\"var-key\">{$keyHtml}</td><td class=\"var-value\">{$valueHtml}</td></tr>";
        }
        $html .= '</table>';

        return $html;
    }

    /**
     * Affiche une page d'erreur détaillée (mode dev)
     */
    private function renderDebugPage(Throwable $exception): void
    {
        // Nettoyer tous les buffers de sortie pour éviter d'afficher l'erreur au milieu d'une page
        while (ob_get_level()) {
            ob_end_clean();
        }

        $class = get_class($exception);
        $message = htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
        $file = $exception->getFile();
        $line = $exception->getLine();
        $shortClass = substr(strrchr($class, '\\'), 1) ?: $class;
        $codeExcerpt = $this->getCodeExcerpt($file, $line);
        $enhancedTrace = $this->getEnhancedStackTrace($exception);
        $contextVars = $this->getContextVariables();
        $fileHtml = htmlspecialchars($file, ENT_QUOTES, 'UTF-8');
        $classHtml = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');

        echo <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erreur - {$shortClass}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'JetBrains Mono', 'Fira Code', Monaco, monospace; 
            background: linear-gradient(135deg, #1e1e2e 0%, #2d2d3f 100%);
            color: #cdd6f4;
            min-height: 100vh;
            line-height: 1.5;
        }
        .container { 
            width: 95%; 
            max-width: 1600px; 
            margin: 0 auto; 
            padding: 20px; 
            box-sizing: border-box;
        }
        
        /* Header */
        .header {
            background: linear-gradient(135deg, #f38ba8 0%, #fab387 100%);
            color: #1e1e2e;
            padding: 24px 32px;
            border-radius: 12px 12px 0 0;
            margin-bottom: 0;
        }
        .header h1 { font-size: 24px; font-weight: 700; margin-bottom: 8px; }
        .header .exception-class { 
            background: rgba(0,0,0,0.2); 
            padding: 4px 12px; 
            border-radius: 6px; 
            font-size: 14px;
        }
        
        /* Message */
        .message-box {
            background: #313244;
            border-left: 4px solid #f9e2af;
            padding: 20px 24px;
            font-size: 16px;
            word-break: break-word;
        }
        
        /* Content */
        .content { background: #1e1e2e; padding: 24px; border-radius: 0 0 12px 12px; }
        
        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .info-item { background: #313244; padding: 12px 16px; border-radius: 8px; }
        .info-label { color: #a6adc8; font-size: 11px; text-transform: uppercase; margin-bottom: 4px; }
        .info-value { color: #89b4fa; font-size: 13px; word-break: break-all; }
        
        /* Code Excerpt */
        .section { margin-bottom: 24px; }
        .section-title { 
            color: #89b4fa; 
            font-size: 14px; 
            font-weight: 600; 
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .code-excerpt { 
            background: #11111b; 
            border-radius: 8px; 
            overflow: hidden;
            font-size: 12px;
        }
        .code-line { 
            display: flex; 
            padding: 2px 0;
            border-left: 3px solid transparent;
        }
        .code-line.error-line { 
            background: rgba(243, 139, 168, 0.15); 
            border-left-color: #f38ba8;
        }
        .line-marker { width: 24px; text-align: center; color: #f38ba8; }
        .line-num { 
            width: 48px; 
            text-align: right; 
            padding-right: 16px; 
            color: #6c7086;
            user-select: none;
        }
        .line-code { flex: 1; white-space: pre; overflow-x: auto; }
        
        /* Stack Trace */
        .trace-frame { 
            background: #313244; 
            margin-bottom: 4px; 
            border-radius: 6px; 
            overflow: hidden;
        }
        .trace-header { 
            display: flex; 
            gap: 12px; 
            padding: 10px 14px; 
            cursor: pointer;
            transition: background 0.2s;
        }
        .trace-header:hover { background: #45475a; }
        .trace-num { color: #f38ba8; font-weight: bold; min-width: 30px; }
        .trace-call { color: #a6e3a1; flex: 1; }
        .trace-location { color: #89b4fa; font-size: 12px; }
        .trace-code { padding: 0 14px 14px; }
        .trace-code .code-excerpt { font-size: 11px; }
        .hidden { display: none; }
        
        /* Context Variables */
        .context-section { margin-bottom: 8px; }
        .context-section summary {
            background: #313244;
            padding: 10px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
        }
        .context-section summary:hover { background: #45475a; }
        .context-section[open] summary { border-radius: 6px 6px 0 0; }
        .var-table { 
            width: 100%; 
            background: #11111b; 
            border-radius: 0 0 6px 6px;
            font-size: 12px;
        }
        .var-table td { padding: 8px 12px; border-bottom: 1px solid #313244; }
        .var-key { color: #89dceb; width: 200px; }
        .var-value { color: #f9e2af; word-break: break-all; }
        .var-value pre { margin: 0; font-size: 11px; max-height: 150px; overflow: auto; }
        .bool { color: #fab387; }
        .null { color: #6c7086; font-style: italic; }
        .empty { color: #6c7086; font-style: italic; display: block; padding: 12px; }
        
        /* Footer */
        .footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 0;
            color: #6c7086;
            font-size: 12px;
        }
        .copy-btn {
            background: #45475a;
            color: #cdd6f4;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-family: inherit;
            font-size: 12px;
        }
        .copy-btn:hover { background: #585b70; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚨 Une erreur s'est produite</h1>
            <span class="exception-class">{$shortClass}</span>
        </div>
        
        <div class="message-box">{$message}</div>
        
        <div class="content">
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Fichier</div>
                    <div class="info-value">{$fileHtml}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Ligne</div>
                    <div class="info-value">{$line}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Classe</div>
                    <div class="info-value">{$classHtml}</div>
                </div>
            </div>
            
            <div class="section">
                <div class="section-title">📄 Code Source</div>
                {$codeExcerpt}
            </div>
            
            <div class="section">
                <div class="section-title">📚 Stack Trace (cliquez pour voir le code)</div>
                {$enhancedTrace}
            </div>
            
            <div class="section">
                <div class="section-title">🔍 Variables de Contexte</div>
                {$contextVars}
            </div>
        </div>
        
        <div class="footer">
            <span>Framework Ogan 🐕 | Mode DEBUG</span>
            <button class="copy-btn" onclick="copyError()">📋 Copier l'erreur</button>
        </div>
    </div>
    
    <script>
    function copyError() {
        var exClass = document.querySelector('.exception-class').textContent;
        var message = document.querySelector('.message-box').textContent;
        var fileInfo = document.querySelector('.info-value').textContent;
        var text = exClass + ": " + message + "\\n" + "File: " + fileInfo;
        navigator.clipboard.writeText(text).then(function() {
            alert('Erreur copiée !');
        });
    }
    </script>
</body>
</html>
HTML;
    }

    /**
     * Affiche une page d'erreur générique (mode production)
     */
    private function renderProductionPage(Throwable $exception, int $statusCode): void
    {
        // Essayer d'utiliser les templates Ogan si disponibles
        $templateName = match ($statusCode) {
            403 => 'errors/403.ogan',
            404 => 'errors/404.ogan',
            default => 'errors/500.ogan',
        };

        try {
            $templatesPath = \Ogan\Config\Config::get('view.templates_path', 'templates');
            $templateFile = rtrim($templatesPath, '/') . '/' . $templateName;

            // Debug logging pour diagnostiquer le problème
            $logPath = \Ogan\Config\Config::get('log.path', null);
            if ($logPath && class_exists(\Ogan\Logger\Logger::class)) {
                $logger = new \Ogan\Logger\Logger($logPath);
                $logger->debug('ErrorHandler::renderProductionPage', [
                    'statusCode' => $statusCode,
                    'templateName' => $templateName,
                    'templatesPath' => $templatesPath,
                    'templateFile' => $templateFile,
                    'file_exists' => file_exists($templateFile),
                    'is_readable' => is_readable($templateFile),
                ]);
            }

            if (file_exists($templateFile)) {
                $view = new \Ogan\View\View($templatesPath, true);

                // Injecter les services du Container si disponible
                // Cela permet aux templates d'erreur d'utiliser extend(), route(), etc.
                if (self::$container !== null) {
                    // Router pour route(), path(), url()
                    if (self::$container->has(\Ogan\Router\Router::class)) {
                        $view->setRouter(self::$container->get(\Ogan\Router\Router::class));
                    }

                    // Session pour les flash messages et app.session
                    if (self::$container->has(\Ogan\Session\SessionInterface::class)) {
                        $view->setSession(self::$container->get(\Ogan\Session\SessionInterface::class));
                    }

                    // Request pour app.request
                    if (self::$container->has(\Ogan\Http\Request::class)) {
                        $request = self::$container->get(\Ogan\Http\Request::class);
                        $view->setRequest($request);

                        // Récupérer l'utilisateur depuis la Request si disponible
                        if (method_exists($request, 'getUser')) {
                            $view->setUser($request->getUser());
                        }
                    }

                    // CsrfManager pour csrf_token()
                    if (self::$container->has(\Ogan\Security\CsrfManager::class)) {
                        $view->setCsrfTokenManager(self::$container->get(\Ogan\Security\CsrfManager::class));
                    }
                }

                $message = $statusCode === 403
                    ? 'Accès refusé.'
                    : ($statusCode === 404
                        ? 'La page que vous recherchez n\'existe pas.'
                        : 'Une erreur s\'est produite.');

                echo $view->render($templateName, ['message' => $message]);
                return;
            }
        } catch (Throwable $e) {
            // Log l'erreur de rendu du template
            $logPath = \Ogan\Config\Config::get('log.path', null);
            if ($logPath && class_exists(\Ogan\Logger\Logger::class)) {
                $logger = new \Ogan\Logger\Logger($logPath);
                $logger->error('ErrorHandler: échec du rendu du template custom', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
            // Fallback vers HTML inline si le template échoue
        }

        // Fallback HTML inline (quand les templates ne sont pas disponibles)
        $title = $statusCode === 404 ? 'Page non trouvée' : 'Erreur serveur';
        $message = $statusCode === 404
            ? 'La page que vous recherchez n\'existe pas.'
            : 'Une erreur s\'est produite. Veuillez réessayer plus tard.';
        $icon = $statusCode === 404 ? 'M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' : 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z';

        echo <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-600 min-h-screen flex items-center justify-center px-4">
    <div class="text-center text-white max-w-2xl">
        <div class="mb-8">
            <div class="text-9xl font-bold opacity-20 mb-4">{$statusCode}</div>
            <div class="flex justify-center mb-6">
                <svg class="w-24 h-24 text-white/90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{$icon}"></path>
                </svg>
            </div>
        </div>
        <h1 class="text-4xl md:text-5xl font-bold mb-4">{$title}</h1>
        <p class="text-xl md:text-2xl opacity-90 mb-8">{$message}</p>
        <a href="/" class="inline-flex items-center gap-2 bg-white/20 hover:bg-white/30 backdrop-blur-sm text-white font-semibold px-6 py-3 rounded-lg transition-all duration-200 shadow-lg hover:shadow-xl">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Retour à l'accueil
        </a>
        <div class="mt-12 text-white/60 text-sm">
            Framework Ogan 🐕
        </div>
    </div>
</body>
</html>
HTML;
    }
}
